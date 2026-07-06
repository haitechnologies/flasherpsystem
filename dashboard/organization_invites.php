<?php


use App\Core\DB;
use App\Core\Session;
use App\Security\Roles;
include('admin_elements/admin_header.php');

$module = 'organizations';
$module_caption = 'Organization Invites';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$organizationId = (int)($_GET['organization_id'] ?? dashboardGetActiveOrganizationId());
$inviteEmail = '';
$inviteRoleId = 0;

if ($organizationId <= 0) {
    flash_error('Please select an organization first.');
    header('Location:listing_organizations.php');
    exit;
}

if (!dashboardUserBelongsToOrganization($organizationId, (int)Session::userId()) && !Roles::currentUserHasFullAccess()) {
    flash_error('You do not have access to that organization.');
    header('Location:index.php');
    exit;
}

$organizationStmt = $mysqli->prepare("SELECT warehouse_name FROM `" . DB::ORGANIZATIONS . "` WHERE id = ? LIMIT 1");
$organizationName = 'Organization';
if ($organizationStmt) {
    $organizationStmt->bind_param('i', $organizationId);
    $organizationStmt->execute();
    $organizationRow = $organizationStmt->get_result()->fetch_assoc();
    $organizationStmt->close();
    if (!empty($organizationRow['warehouse_name'])) {
        $organizationName = (string)$organizationRow['warehouse_name'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $inviteAction = trim((string)($_POST['invite_action'] ?? 'create'));
        if ($inviteAction === 'resend') {
            $inviteId = (int)($_POST['invite_id'] ?? 0);
            $result = dashboardResendOrganizationInvite($organizationId, $inviteId);
        } else if ($inviteAction === 'revoke') {
            $inviteId = (int)($_POST['invite_id'] ?? 0);
            $result = dashboardRevokeOrganizationInvite($organizationId, $inviteId);
        } else if ($inviteAction === 'send_now') {
            $inviteId = (int)($_POST['invite_id'] ?? 0);
            $queueId = (int)($_POST['queue_id'] ?? 0);
            $result = dashboardSendOrganizationInviteEmailNow($organizationId, $inviteId, $queueId);
        } else {
            $inviteEmail = trim((string)($_POST['invite_email'] ?? ''));
            $inviteRoleId = (int)($_POST['organization_role_id'] ?? 0);
            $result = dashboardCreateOrganizationInvite($organizationId, $inviteEmail, $inviteRoleId > 0 ? $inviteRoleId : null);
        }

        if (!empty($result['success'])) {
            $success_message = (string)($result['message'] ?? 'Invite action completed successfully.');
            if ($inviteAction === 'create') {
                $inviteEmail = '';
                $inviteRoleId = 0;
            }
        } else {
            $error_message = (string)($result['message'] ?? 'Unable to complete invite action.');
        }
    }
}

$roles = [];
$rolesStmt = $mysqli->prepare(
    "SELECT id, role_name FROM `" . DB::ORGANIZATION_ROLES . "` WHERE organization_id = ? AND is_active = 1 ORDER BY role_name ASC"
);
if ($rolesStmt) {
    $rolesStmt->bind_param('i', $organizationId);
    $rolesStmt->execute();
    $rolesResult = $rolesStmt->get_result();
    while ($row = $rolesResult ? $rolesResult->fetch_assoc() : null) {
        $roles[] = $row;
    }
    $rolesStmt->close();
}

$invites = [];
$invitesStmt = $mysqli->prepare(
    "SELECT oi.id, oi.email, oi.invite_token, oi.invite_status, oi.expires_at, oi.created_at, u.full_name AS invited_by_name, r.role_name
     FROM `" . DB::ORGANIZATION_INVITES . "` oi
     LEFT JOIN `" . DB::USERS . "` u ON u.id = oi.invited_by
     LEFT JOIN `" . DB::ORGANIZATION_ROLES . "` r ON r.id = oi.role_id
     WHERE oi.organization_id = ?
     ORDER BY oi.created_at DESC
     LIMIT 25"
);
if ($invitesStmt) {
    $invitesStmt->bind_param('i', $organizationId);
    $invitesStmt->execute();
    $invitesResult = $invitesStmt->get_result();
    while ($row = $invitesResult ? $invitesResult->fetch_assoc() : null) {
        $invites[] = $row;
    }
    $invitesStmt->close();
}

$inviteDeliveryByToken = [];
$inviteDeliveryHistoryByToken = [];
if (!empty($invites)) {
    $tokenConditions = [];
    foreach ($invites as $inviteRow) {
        $token = trim((string)($inviteRow['invite_token'] ?? ''));
        if ($token === '') {
            continue;
        }
        $tokenPattern = '"X-Invite-Token":"' . $token . '"';
        $tokenConditions[] = "headers LIKE '%" . $mysqli->real_escape_string($tokenPattern) . "%'";
    }

    if (!empty($tokenConditions)) {
        $queueQuery = "SELECT id, status, created_at, sent_at, failed_reason, headers
                       FROM `" . DB::EMAIL_QUEUE . "`
                       WHERE headers LIKE '%\"X-Invite-Type\":\"organization\"%'
                         AND (" . implode(' OR ', $tokenConditions) . ")
                       ORDER BY id DESC
                       LIMIT 200";
        $queueResult = $mysqli->query($queueQuery);
        while ($queueRow = $queueResult instanceof mysqli_result ? $queueResult->fetch_assoc() : null) {
            $headers = json_decode((string)($queueRow['headers'] ?? ''), true);
            if (!is_array($headers)) {
                continue;
            }

            $token = trim((string)($headers['X-Invite-Token'] ?? ''));
            if ($token === '') {
                continue;
            }

            if (!isset($inviteDeliveryHistoryByToken[$token])) {
                $inviteDeliveryHistoryByToken[$token] = [];
            }
            if (count($inviteDeliveryHistoryByToken[$token]) < 5) {
                $inviteDeliveryHistoryByToken[$token][] = $queueRow;
            }

            if (isset($inviteDeliveryByToken[$token])) {
                continue;
            }
            $inviteDeliveryByToken[$token] = $queueRow;
        }
    }
}

$activeMembers = dashboardOrganizationActiveMemberCount($organizationId);
$maxTeamMembers = dashboardMaxTeamMembers();

?>

<div class="content-wrapper">
        <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1">
                <h1 class="h5 mb-0 d-inline-flex align-items-center gap-2">
                    <a href="listing_<?php echo $module; ?>.php" class="text-dark">All <?php echo ucwords(str_ireplace('_', " ", $module)); ?></a>
                </h1>
            </div>

            <div class="my-1">
                <?php if (empty($hide_add_button) && isset($module_id) && isset($module) && granted('create', $module_id)) { ?>
                    <a href="<?php echo $module; ?>.php" class="btn btn-primary btn-sm d-inline-flex align-items-center">
                        <i class="ph-plus ph-sm me-2 opacity-75"></i>New
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>
    <!-- /page header -->

    <div class="content">
        <?php include('admin_elements/breadcrumb.php'); ?>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Invite Members to <?php echo htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8'); ?></h5>
                <a href="organizations.php?action=edit_organizations&id=<?php echo $organizationId; ?>" class="btn btn-light btn-sm">Back to Organization</a>
            </div>
            <div class="card-body">
                <div class="mb-3 text-muted">
                    Active members: <?php echo number_format($activeMembers); ?>
                    <?php if ($maxTeamMembers > 0) { ?>
                        / <?php echo number_format($maxTeamMembers); ?>
                    <?php } ?>
                </div>

                <form method="post" novalidate>
                    <?php echo csrf_field(); ?>
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-5">
                            <label class="form-label">Invite Email</label>
                            <input type="email" name="invite_email" class="form-control" value="<?php echo htmlspecialchars($inviteEmail, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Organization Role</label>
                            <select name="organization_role_id" class="form-select">
                                <option value="0">No role assigned yet</option>
                                <?php foreach ($roles as $role) { ?>
                                    <option value="<?php echo (int)$role['id']; ?>" <?php echo $inviteRoleId === (int)$role['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$role['role_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <button type="submit" class="btn btn-primary w-100">Create Invite</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Invites</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Delivery</th>
                                <th>Invited By</th>
                                <th>Expires</th>
                                <th>Accept Link</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invites)) { ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No organization invites yet.</td>
                                </tr>
                            <?php } else { ?>
                                <?php foreach ($invites as $invite) { ?>
                                    <?php $isPendingInvite = ((string)($invite['invite_status'] ?? '') === 'pending'); ?>
                                    <?php $delivery = $inviteDeliveryByToken[(string)($invite['invite_token'] ?? '')] ?? null; ?>
                                    <?php $deliveryHistory = $inviteDeliveryHistoryByToken[(string)($invite['invite_token'] ?? '')] ?? []; ?>
                                    <?php
                                    $deliveryStatus = strtolower(trim((string)($delivery['status'] ?? '')));
                                    $canSendNow = $isPendingInvite && is_array($delivery) && in_array($deliveryStatus, ['pending', 'retry', 'queued', 'failed'], true);
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)$invite['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)($invite['role_name'] ?? 'Unassigned'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)$invite['invite_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if (is_array($delivery)) { ?>
                                                <?php if ($deliveryStatus === 'sent') { ?>
                                                    <span class="badge bg-success bg-opacity-20 text-success">Sent</span>
                                                <?php } else if ($deliveryStatus === 'failed') { ?>
                                                    <span class="badge bg-danger bg-opacity-20 text-danger">Failed</span>
                                                <?php } else if ($deliveryStatus === 'retry') { ?>
                                                    <span class="badge bg-warning bg-opacity-20 text-warning">Retry</span>
                                                <?php } else { ?>
                                                    <span class="badge bg-secondary bg-opacity-20 text-secondary"><?php echo htmlspecialchars(ucfirst($deliveryStatus), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php } ?>
                                                <div class="small text-muted mt-1">
                                                    <?php if (!empty($delivery['sent_at'])) { ?>
                                                        <?php echo htmlspecialchars((string)$delivery['sent_at'], ENT_QUOTES, 'UTF-8'); ?>
                                                    <?php } else { ?>
                                                        Queued: <?php echo htmlspecialchars((string)($delivery['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
                                                    <?php } ?>
                                                </div>
                                                <?php $deliveryError = trim((string)($delivery['failed_reason'] ?? '')); ?>
                                                <?php if ($deliveryError !== '') { ?>
                                                    <?php
                                                    $errorPreview = strlen($deliveryError) > 90
                                                        ? substr($deliveryError, 0, 90) . '...'
                                                        : $deliveryError;
                                                    ?>
                                                    <div class="small text-danger mt-1" title="<?php echo htmlspecialchars($deliveryError, ENT_QUOTES, 'UTF-8'); ?>">
                                                        Error: <?php echo htmlspecialchars($errorPreview, ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                <?php } ?>
                                                <?php if (count($deliveryHistory) > 1) { ?>
                                                    <details class="mt-1">
                                                        <summary class="small text-muted">History (<?php echo count($deliveryHistory); ?>)</summary>
                                                        <div class="small mt-1">
                                                            <?php foreach ($deliveryHistory as $historyRow) { ?>
                                                                <?php
                                                                $historyStatus = strtolower(trim((string)($historyRow['status'] ?? 'pending')));
                                                                $historyWhen = !empty($historyRow['sent_at']) ? (string)$historyRow['sent_at'] : (string)($historyRow['created_at'] ?? '-');
                                                                ?>
                                                                <div>
                                                                    #<?php echo (int)($historyRow['id'] ?? 0); ?>
                                                                    | <?php echo htmlspecialchars($historyStatus, ENT_QUOTES, 'UTF-8'); ?>
                                                                    | <?php echo htmlspecialchars($historyWhen, ENT_QUOTES, 'UTF-8'); ?>
                                                                </div>
                                                            <?php } ?>
                                                        </div>
                                                    </details>
                                                <?php } ?>
                                            <?php } else { ?>
                                                <span class="text-muted">Not queued</span>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo htmlspecialchars((string)($invite['invited_by_name'] ?? 'System'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)$invite['expires_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if ($isPendingInvite) { ?>
                                                <input type="text" class="form-control form-control-sm" readonly value="<?php echo htmlspecialchars($admin_base_url . '/organization_accept_invite.php?token=' . (string)$invite['invite_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php } else { ?>
                                                <span class="text-muted">-</span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php if ($isPendingInvite) { ?>
                                                <div class="d-flex gap-1">
                                                    <?php if ($canSendNow) { ?>
                                                        <form method="post" class="m-0">
                                                            <?php echo csrf_field(); ?>
                                                            <input type="hidden" name="invite_action" value="send_now">
                                                            <input type="hidden" name="invite_id" value="<?php echo (int)$invite['id']; ?>">
                                                            <input type="hidden" name="queue_id" value="<?php echo (int)($delivery['id'] ?? 0); ?>">
                                                            <button type="submit" class="btn btn-outline-success btn-sm">Send Now</button>
                                                        </form>
                                                    <?php } ?>
                                                    <form method="post" class="m-0">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="invite_action" value="resend">
                                                        <input type="hidden" name="invite_id" value="<?php echo (int)$invite['id']; ?>">
                                                        <button type="submit" class="btn btn-outline-primary btn-sm">Resend</button>
                                                    </form>
                                                    <form method="post" class="m-0" onsubmit="return confirm('Revoke this invite?');">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="invite_action" value="revoke">
                                                        <input type="hidden" name="invite_id" value="<?php echo (int)$invite['id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">Revoke</button>
                                                    </form>
                                                </div>
                                            <?php } else { ?>
                                                <span class="text-muted">-</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php include('admin_elements/copyright.php'); ?>
</div>

<?php include('admin_elements/admin_footer.php'); ?>

<?php include('admin_elements/admin_footer.php'); ?>