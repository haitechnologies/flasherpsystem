<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class UserDocumentsDataTable extends BaseDataTable
{
    protected $table = DB::USER_DOCUMENTS;
    protected $searchFields = ['display_name', 'filename'];
    protected $sortableColumns = [0 => 'ud.id', 1 => 'ud.document_category', 2 => 'ud.attachable_id', 3 => 'ud.filename', 4 => 'ud.issued_date', 5 => 'ud.expiry_date', 6 => 'ud.expiry_date', 7 => 'ud.created_at'];

    protected function buildBaseQuery($requestData)
    {
        return "SELECT ud.*, ud.display_name AS document_name, ud.filename AS document_filename, dc.document_category AS category_name, u.full_name "
            . "FROM `" . DB::USER_DOCUMENTS . "` ud "
            . "LEFT JOIN `" . DB::DOCUMENT_CATEGORIES . "` dc ON dc.id = ud.document_category "
            . "LEFT JOIN `" . DB::USERS . "` u ON u.id = ud.attachable_id "
            . "WHERE ud.attachable_type = 'UserDoc' AND ud.id > 0" . $this->getOrgIdWhereClause();
    }

    protected function buildOrderClause($requestData): string
    {
        $categoryOrder = "CASE dc.document_category
            WHEN 'Emirates ID' THEN 1
            WHEN 'Visa' THEN 2
            WHEN 'Labor Card' THEN 3
            WHEN 'Passport' THEN 4
            WHEN 'Photo' THEN 5
            WHEN 'Contract' THEN 6
            ELSE 7
        END";

        $orderColumn = (int)($requestData['order'][0]['column'] ?? 0);
        $orderDir = strtoupper($requestData['order'][0]['dir'] ?? 'ASC');

        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'ASC';
        }

        $column = $this->sortableColumns[$orderColumn] ?? 'ud.id';
        return 'ORDER BY ' . $categoryOrder . ' ASC, ' . $column . ' ' . $orderDir;
    }

    protected function getOrgIdWhereClause(): string
    {
        if ($this->organizationId === null) {
            return '';
        }
        $this->params['active_org_id'] = (int)$this->organizationId;
        return " AND ud.organization_id = :active_org_id";
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)($row['id'] ?? 0);
        $fileName = trim((string)($row['document_filename'] ?? ''));
        $fileLink = '';
        if ($fileName !== '') {
            $fileLink = '<a href="../uploads/user_documents/' . rawurlencode($fileName) . '" target="_blank" rel="noopener">View</a>';
        }

        $issueDate = (string)($row['issued_date'] ?? '');
        $expiryDate = (string)($row['expiry_date'] ?? '');
        $statusBadge = $this->getStatusBadge($expiryDate);

        return [
            $this->rowNumber,
            htmlspecialchars((string)($row['category_name'] ?? '')),
            '<span data-employee-id="' . ((int)($row['attachable_id'] ?? 0)) . '">' . htmlspecialchars((string)($row['full_name'] ?? '')) . '</span>',
            $fileLink,
            $this->formatDateDMY($issueDate),
            $this->formatDateDMY($expiryDate),
            $statusBadge,
            $this->formatTimeAgo((string)($row['created_at'] ?? '')),
        ];
    }

    private function formatDateDMY(string $date): string
    {
        if ($date === '' || $date === '0000-00-00' || $date === '1970-01-01') {
            return '';
        }
        $ts = strtotime($date);
        return $ts !== false ? date('d M Y', $ts) : $date;
    }

    private function getStatusBadge(string $expiryDate): string
    {
        if ($expiryDate === '' || $expiryDate === '0000-00-00' || $expiryDate === '1970-01-01') {
            return '<span class="badge bg-secondary bg-opacity-20 text-secondary">N/A</span>';
        }
        $expTs = strtotime($expiryDate);
        if ($expTs === false) {
            return '<span class="badge bg-secondary bg-opacity-20 text-secondary">N/A</span>';
        }
        $now = time();
        $diffDays = floor(($expTs - $now) / 86400);

        if ($diffDays < 0) {
            return '<span class="badge bg-danger bg-opacity-20 text-danger">Expired</span>';
        }
        if ($diffDays <= 30) {
            return '<span class="badge bg-warning bg-opacity-20 text-warning">Near Expiry</span>';
        }
        return '<span class="badge bg-success bg-opacity-20 text-success">Up to Date</span>';
    }

    protected function getActionButtons($id, $module)
    {
        $actions = '';
        if ($this->isGranted('edit', $module)) {
            $actions .= ActionButtonHelper::editButton((int)$id, 'user_documents.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $actions .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return trim($actions);
    }
}
