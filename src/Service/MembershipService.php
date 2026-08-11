<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Container;
use App\Core\Database;
use App\Core\DB;
use App\Core\ErrorCapture;
use Throwable;

class MembershipService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            try {
                $container = Container::getInstance();
                if ($container->has(Database::class)) {
                    $this->db = $container->get(Database::class);
                } else {
                    $this->db = new Database();
                }
            } catch (Throwable $e) {
                $this->db = new Database();
            }
        }
    }

    public function hasActiveMembership(int $organizationId, int $userId): bool
    {
        return $this->userBelongsToOrganization($organizationId, $userId);
    }

    public function countActiveMembers(int $organizationId): int
    {
        $sql = "SELECT COUNT(*) AS cnt FROM `" . DB::ORGANIZATION_MEMBERSHIPS . "` WHERE organization_id = ? AND membership_status = 'active'";
        try {
            $row = $this->db->fetchOne($sql, [$organizationId]);
            return (int)($row['cnt'] ?? 0);
        } catch (Throwable $e) {
            ErrorCapture::record('MembershipService::countActiveMembers() failed: ' . $e->getMessage());
            return 0;
        }
    }

    public function createInvite(
        int $organizationId,
        int $inviterUserId,
        string $email,
        ?int $roleId = null,
        int $expiresInDays = 7
    ): array {
        $email = strtolower(trim($email));
        if ($organizationId <= 0 || $inviterUserId <= 0) {
            return ['success' => false, 'message' => 'Invalid organization or inviter.'];
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'A valid email address is required.'];
        }

        if (!$this->userBelongsToOrganization($organizationId, $inviterUserId)) {
            return ['success' => false, 'message' => 'You do not belong to this organization.'];
        }

        $existingUserId = $this->findUserIdByEmail($email);
        if ($existingUserId > 0 && $this->userBelongsToOrganization($organizationId, $existingUserId)) {
            return ['success' => false, 'message' => 'This user is already a member of the organization.'];
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . max(1, $expiresInDays) . ' days'));

        $existingInvite = $this->findPendingInviteByEmail($organizationId, $email);
        if ($existingInvite) {
            $inviteId = (int)$existingInvite['id'];
            $sql = "UPDATE `" . DB::ORGANIZATION_INVITES . "`
                    SET invite_token = ?, invited_by = ?, role_id = ?, invite_status = 'pending', expires_at = ?, accepted_at = NULL
                    WHERE id = ?";
            try {
                $this->db->execute($sql, [$token, $inviterUserId, $roleId, $expiresAt, $inviteId]);
                return [
                    'success' => true,
                    'message' => 'Organization invite refreshed successfully.',
                    'invite_token' => $token,
                    'invite_id' => $inviteId,
                    'email' => $email,
                ];
            } catch (Throwable $e) {
                ErrorCapture::record('MembershipService::createInvite() update failed: ' . $e->getMessage());
                return ['success' => false, 'message' => 'Failed to refresh organization invite.'];
            }
        }

        $sql = "INSERT INTO `" . DB::ORGANIZATION_INVITES . "`
                (organization_id, email, invite_token, invited_by, role_id, invite_status, expires_at)
                VALUES (?, ?, ?, ?, ?, 'pending', ?)";
        try {
            $inviteId = (int)$this->db->insert($sql, [$organizationId, $email, $token, $inviterUserId, $roleId, $expiresAt]);
            return [
                'success' => true,
                'message' => 'Organization invite created successfully.',
                'invite_token' => $token,
                'invite_id' => $inviteId,
                'email' => $email,
            ];
        } catch (Throwable $e) {
            ErrorCapture::record('MembershipService::createInvite() insert failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create organization invite.'];
        }
    }

    public function acceptInviteByToken(string $token, int $userId): array
    {
        $token = trim($token);
        if ($token === '' || $userId <= 0) {
            return ['success' => false, 'message' => 'Invalid invite token or user.'];
        }

        $invite = $this->findInviteByToken($token);
        if (!$invite) {
            return ['success' => false, 'message' => 'Invite not found.'];
        }

        if (($invite['invite_status'] ?? '') !== 'pending') {
            return ['success' => false, 'message' => 'Invite is no longer active.'];
        }

        if (!empty($invite['expires_at']) && strtotime((string)$invite['expires_at']) < time()) {
            $this->markInviteExpired((int)$invite['id']);
            return ['success' => false, 'message' => 'Invite has expired.'];
        }

        $userEmail = $this->findUserEmailById($userId);
        if ($userEmail === '' || strtolower($userEmail) !== strtolower((string)$invite['email'])) {
            return ['success' => false, 'message' => 'Invite email does not match the current user.'];
        }

        $organizationId = (int)$invite['organization_id'];
        if ($this->userBelongsToOrganization($organizationId, $userId)) {
            $this->markInviteAccepted((int)$invite['id']);
            return ['success' => true, 'message' => 'User already belongs to this organization.'];
        }

        $sql = "INSERT INTO `" . DB::ORGANIZATION_MEMBERSHIPS . "` (organization_id, user_id, membership_status, is_owner, invited_by, joined_at) VALUES (?, ?, 'active', 0, ?, NOW())";
        try {
            $invitedBy = (int)($invite['invited_by'] ?? 0);
            $membershipId = (int)$this->db->insert($sql, [$organizationId, $userId, $invitedBy]);

            $roleId = (int)($invite['role_id'] ?? 0);
            if ($roleId > 0) {
                $roleSql = "INSERT IGNORE INTO `" . DB::ORGANIZATION_MEMBER_ROLES . "` (membership_id, role_id) VALUES (?, ?)";
                try {
                    $this->db->execute($roleSql, [$membershipId, $roleId]);
                } catch (Throwable $e) {
                    ErrorCapture::record('MembershipService::acceptInviteByToken() role assignment failed: ' . $e->getMessage());
                }
            }

            $this->markInviteAccepted((int)$invite['id']);
            return ['success' => true, 'message' => 'Organization invite accepted successfully.', 'organization_id' => $organizationId];
        } catch (Throwable $e) {
            ErrorCapture::record('MembershipService::acceptInviteByToken() membership insert failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create organization membership.'];
        }
    }

    public function resendInvite(
        int $organizationId,
        int $inviteId,
        int $requesterUserId,
        ?int $roleId = null,
        int $expiresInDays = 7,
        bool $allowWithoutMembership = false
    ): array {
        if ($organizationId <= 0 || $inviteId <= 0 || $requesterUserId <= 0) {
            return ['success' => false, 'message' => 'Invalid organization, invite, or user.'];
        }

        if (!$allowWithoutMembership && !$this->userBelongsToOrganization($organizationId, $requesterUserId)) {
            return ['success' => false, 'message' => 'You do not belong to this organization.'];
        }

        $invite = $this->findPendingInviteById($organizationId, $inviteId);
        if (!$invite) {
            return ['success' => false, 'message' => 'Only pending invites can be resent.'];
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . max(1, $expiresInDays) . ' days'));
        $resolvedRoleId = $roleId !== null ? $roleId : (int)($invite['role_id'] ?? 0);

        $sql = "UPDATE `" . DB::ORGANIZATION_INVITES . "`
                SET invite_token = ?, invited_by = ?, role_id = ?, invite_status = 'pending', expires_at = ?, accepted_at = NULL
                WHERE id = ? AND organization_id = ?";
        try {
            $this->db->execute($sql, [$token, $requesterUserId, $resolvedRoleId, $expiresAt, $inviteId, $organizationId]);
            return [
                'success' => true,
                'message' => 'Organization invite resent successfully.',
                'invite_id' => $inviteId,
                'invite_token' => $token,
                'email' => (string)($invite['email'] ?? ''),
            ];
        } catch (Throwable $e) {
            ErrorCapture::record('MembershipService::resendInvite() failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to resend organization invite.'];
        }
    }

    public function revokeInvite(
        int $organizationId,
        int $inviteId,
        int $requesterUserId,
        bool $allowWithoutMembership = false
    ): array {
        if ($organizationId <= 0 || $inviteId <= 0 || $requesterUserId <= 0) {
            return ['success' => false, 'message' => 'Invalid organization, invite, or user.'];
        }

        if (!$allowWithoutMembership && !$this->userBelongsToOrganization($organizationId, $requesterUserId)) {
            return ['success' => false, 'message' => 'You do not belong to this organization.'];
        }

        $invite = $this->findPendingInviteById($organizationId, $inviteId);
        if (!$invite) {
            return ['success' => false, 'message' => 'Only pending invites can be revoked.'];
        }

        $sql = "UPDATE `" . DB::ORGANIZATION_INVITES . "`
                SET invite_status = 'revoked', accepted_at = NULL
                WHERE id = ? AND organization_id = ? AND invite_status = 'pending'";
        try {
            $this->db->execute($sql, [$inviteId, $organizationId]);
            return ['success' => true, 'message' => 'Organization invite revoked successfully.', 'invite_id' => $inviteId];
        } catch (Throwable $e) {
            ErrorCapture::record('MembershipService::revokeInvite() failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to revoke organization invite.'];
        }
    }

    private function userBelongsToOrganization(int $organizationId, int $userId): bool
    {
        $sql = "SELECT id FROM `" . DB::ORGANIZATION_MEMBERSHIPS . "` WHERE organization_id = ? AND user_id = ? AND membership_status = 'active' LIMIT 1";
        try {
            $row = $this->db->fetchOne($sql, [$organizationId, $userId]);
            return !empty($row);
        } catch (Throwable $e) {
            ErrorCapture::record('MembershipService::userBelongsToOrganization() failed: ' . $e->getMessage());
            return false;
        }
    }

    private function findPendingInviteByEmail(int $organizationId, string $email): ?array
    {
        $sql = "SELECT id FROM `" . DB::ORGANIZATION_INVITES . "` WHERE organization_id = ? AND email = ? AND invite_status = 'pending' LIMIT 1";
        try {
            return $this->db->fetchOne($sql, [$organizationId, $email]);
        } catch (Throwable $e) {
            ErrorCapture::record('MembershipService::findPendingInviteByEmail() failed: ' . $e->getMessage());
            return null;
        }
    }

    private function findInviteByToken(string $token): ?array
    {
        $sql = "SELECT * FROM `" . DB::ORGANIZATION_INVITES . "` WHERE invite_token = ? LIMIT 1";
        try {
            return $this->db->fetchOne($sql, [$token]);
        } catch (Throwable $e) {
            ErrorCapture::record('MembershipService::findInviteByToken() failed: ' . $e->getMessage());
            return null;
        }
    }

    private function findPendingInviteById(int $organizationId, int $inviteId): ?array
    {
        $sql = "SELECT id, email, role_id FROM `" . DB::ORGANIZATION_INVITES . "` WHERE id = ? AND organization_id = ? AND invite_status = 'pending' LIMIT 1";
        try {
            return $this->db->fetchOne($sql, [$inviteId, $organizationId]);
        } catch (Throwable $e) {
            ErrorCapture::record('MembershipService::findPendingInviteById() failed: ' . $e->getMessage());
            return null;
        }
    }

    private function findUserIdByEmail(string $email): int
    {
        $sql = "SELECT id FROM `" . DB::USERS . "` WHERE email = ? LIMIT 1";
        try {
            $row = $this->db->fetchOne($sql, [$email]);
            return (int)($row['id'] ?? 0);
        } catch (Throwable $e) {
            ErrorCapture::record('MembershipService::findUserIdByEmail() failed: ' . $e->getMessage());
            return 0;
        }
    }

    private function findUserEmailById(int $userId): string
    {
        $sql = "SELECT email FROM `" . DB::USERS . "` WHERE id = ? LIMIT 1";
        try {
            $row = $this->db->fetchOne($sql, [$userId]);
            return (string)($row['email'] ?? '');
        } catch (Throwable $e) {
            ErrorCapture::record('MembershipService::findUserEmailById() failed: ' . $e->getMessage());
            return '';
        }
    }

    private function markInviteAccepted(int $inviteId): void
    {
        $sql = "UPDATE `" . DB::ORGANIZATION_INVITES . "` SET invite_status = 'accepted', accepted_at = NOW() WHERE id = ?";
        try {
            $this->db->execute($sql, [$inviteId]);
        } catch (Throwable $e) {
            ErrorCapture::record('MembershipService::markInviteAccepted() failed: ' . $e->getMessage());
        }
    }

    private function markInviteExpired(int $inviteId): void
    {
        $sql = "UPDATE `" . DB::ORGANIZATION_INVITES . "` SET invite_status = 'expired' WHERE id = ?";
        try {
            $this->db->execute($sql, [$inviteId]);
        } catch (Throwable $e) {
            ErrorCapture::record('MembershipService::markInviteExpired() failed: ' . $e->getMessage());
        }
    }
}
