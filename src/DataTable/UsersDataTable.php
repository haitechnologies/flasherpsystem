<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class UsersDataTable extends BaseDataTable
{
    protected $table = DB::USERS;

    protected $searchFields = [
        'u.full_name',
        'u.email',
        'u.contact1'
    ];

    protected $sortableColumns = [
        0 => 'id',
        1 => 'full_name',
        2 => 'date_of_joining',
        3 => 'role_id',
        4 => 'is_active',
        5 => 'upcoming_air_ticket_date',
        6 => 'id'
    ];

    protected function getOrgIdWhereClause(): string
    {
        $this->params['active_org_id'] = (int)$this->organizationId;
        return " AND (u.organization_id = :active_org_id OR u.organization_id IS NULL)";
    }

    protected function buildBaseQuery($requestData)
    {
        $orgWhere = $this->getOrgIdWhereClause();
        return "SELECT u.*,
                       (SELECT at.eligibility_date FROM `" . DB::AIR_TICKETS . "` at
                        WHERE at.employee_id = u.id AND at.status != 'cancelled'
                        ORDER BY at.eligibility_date DESC LIMIT 1) as upcoming_air_ticket_date,
                       (SELECT COUNT(*) FROM `" . DB::USER_DOCUMENTS . "` att
                        WHERE att.attachable_type = 'UserDoc' AND att.attachable_id = u.id) as doc_count
                FROM `" . $this->table . "` u WHERE u.id > 0" . $orgWhere;
    }

    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $roleIds = [];
        $deptIds = [];
        $desigIds = [];

        foreach ($rows as $r) {
            $rid = (int)($r['role_id'] ?? 0);
            if ($rid > 0) $roleIds[] = $rid;
            $did = (int)($r['department_id'] ?? 0);
            if ($did > 0) $deptIds[] = $did;
            $sid = (int)($r['designation_id'] ?? 0);
            if ($sid > 0) $desigIds[] = $sid;
        }

        $this->relatedDataCache['roles'] = [];
        $this->relatedDataCache['departments'] = [];
        $this->relatedDataCache['designations'] = [];

        try {
            if (!empty($roleIds)) {
                $unique = array_unique($roleIds);
                $ph = implode(',', array_map(fn($i) => ':r' . $i, array_keys($unique)));
                $p = [];
                foreach ($unique as $i => $id) {
                    $p['r' . $i] = $id;
                }
                $rows = $this->db->fetchAll("SELECT id, role_name FROM " . DB::ROLES . " WHERE id IN ($ph)", $p);
                foreach ($rows as $row) {
                    $this->relatedDataCache['roles'][(int)$row['id']] = $row['role_name'] ?? '-';
                }
            }

            if (!empty($deptIds)) {
                $unique = array_unique($deptIds);
                $ph = implode(',', array_map(fn($i) => ':d' . $i, array_keys($unique)));
                $p = [];
                foreach ($unique as $i => $id) {
                    $p['d' . $i] = $id;
                }
                $rows = $this->db->fetchAll("SELECT id, department FROM " . DB::DEPARTMENTS . " WHERE id IN ($ph)", $p);
                foreach ($rows as $row) {
                    $this->relatedDataCache['departments'][(int)$row['id']] = $row['department'] ?? '-';
                }
            }

            if (!empty($desigIds)) {
                $unique = array_unique($desigIds);
                $ph = implode(',', array_map(fn($i) => ':s' . $i, array_keys($unique)));
                $p = [];
                foreach ($unique as $i => $id) {
                    $p['s' . $i] = $id;
                }
                $rows = $this->db->fetchAll("SELECT id, designation FROM " . DB::DESIGNATIONS . " WHERE id IN ($ph)", $p);
                foreach ($rows as $row) {
                    $this->relatedDataCache['designations'][(int)$row['id']] = $row['designation'] ?? '-';
                }
            }
        } catch (\Throwable $e) {
            ErrorCapture::record("UsersDataTable::prepareRelatedData() failed: " . $e->getMessage());
        }
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        $fullName = (string)($row['full_name'] ?? '');
        $email = (string)($row['email'] ?? '');
        $contact1 = (string)($row['contact1'] ?? '');
        $roleId = (int)$row['role_id'];
        $deptId = (int)($row['department_id'] ?? 0);
        $desigId = (int)($row['designation_id'] ?? 0);
        $dateOfJoining = $row['date_of_joining'] ?? '';
        $isActive = (int)$row['is_active'];
        $airTicketDate = $row['upcoming_air_ticket_date'] ?? '';
        $docCount = (int)($row['doc_count'] ?? 0);

        $roleName = $roleId > 0 && isset($this->relatedDataCache['roles'][$roleId])
            ? $this->relatedDataCache['roles'][$roleId] : '-';

        $deptName = $deptId > 0 && isset($this->relatedDataCache['departments'][$deptId])
            ? $this->relatedDataCache['departments'][$deptId] : '-';

        $desigName = $desigId > 0 && isset($this->relatedDataCache['designations'][$desigId])
            ? $this->relatedDataCache['designations'][$desigId] : '-';

        $dojDisplay = !empty($dateOfJoining) && $dateOfJoining !== '1970-01-01'
            ? date('d M Y', strtotime($dateOfJoining)) : '-';

        $activeBadge = $isActive === 1
            ? BadgeHelper::success('Active')
            : BadgeHelper::danger('Inactive');

        $nameHtml = '<div class="d-flex align-items-center gap-2">';
        $nameHtml .= '<strong>' . htmlspecialchars($fullName ?: '-') . '</strong>';
        if ($docCount > 0) {
            $nameHtml .= ' <i class="ph-paperclip text-secondary" title="' . $docCount . ' document(s) uploaded"></i>';
        }
        $nameHtml .= '</div>';
        $nameHtml .= '<div class="text-muted small">' . htmlspecialchars($email ?: '-');
        if ($deptName !== '-') {
            $nameHtml .= ' · ' . htmlspecialchars($deptName);
        }
        if ($desigName !== '-') {
            $nameHtml .= ' · ' . htmlspecialchars($desigName);
        }
        $nameHtml .= '</div>';
        $nameHtml .= '<div class="text-muted small"><i class="ph-phone"></i> ' . htmlspecialchars($contact1 ?: '-') . '</div>';

        $airTicketDisplay = !empty($airTicketDate)
            ? date('d M Y', strtotime($airTicketDate)) : '-';

        return [
            $this->rowNumber,
            $nameHtml,
            $dojDisplay,
            $roleName,
            $activeBadge,
            $airTicketDisplay,
            $this->getActionButtons($id, 'users')
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $buttons = [];

        if ($this->isGranted('edit', $module)) {
            $buttons[] = ActionButtonHelper::editButton($id, 'users.php', $module, 'Edit', false);
        }

        if ($this->isGranted('delete', $module) && (int)$id !== 1) {
            $buttons[] = ActionButtonHelper::deleteButton($id, $module);
        }

        return implode(' ', array_filter($buttons));
    }
}
