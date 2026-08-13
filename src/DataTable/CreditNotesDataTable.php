<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Core\ErrorCapture;

class CreditNotesDataTable extends BaseDataTable
{
    protected $table = DB::CREDIT_NOTES;
    protected $searchFields = ['credit_note_no', 'reference_no'];
    protected $sortableColumns = [0 => 'credit_note_date', 1 => 'credit_note_no', 2 => 'reference_no', 3 => 'customer_id', 4 => 'credit_note_status', 5 => 'grand_total'];

    protected function buildBaseQuery($requestData)
    {
        return "SELECT id, credit_note_date, credit_note_no, reference_no, customer_id, credit_note_status, grand_total
                FROM `" . DB::CREDIT_NOTES . "` WHERE id > 0" . $this->getOrgIdWhereClause();
    }

    protected function buildSearchClause($requestData)
    {
        $searchValue = $requestData['search']['value'] ?? '';
        if (empty($searchValue)) {
            return '';
        }

        $searchValue = '%' . $searchValue . '%';

        if ($this->organizationId !== null) {
            try {
                $customerRow = $this->db->fetchOne(
                    "SELECT id FROM `" . DB::CUSTOMERS . "` WHERE display_name LIKE :search AND organization_id = :org_id LIMIT 1",
                    ['search' => $searchValue, 'org_id' => $this->organizationId]
                );
                if ($customerRow !== null) {
                    return " AND customer_id = " . (int)$customerRow['id'];
                }
            } catch (\Throwable $e) {
                ErrorCapture::record("CreditNotesDataTable::buildSearchClause customer lookup error: " . $e->getMessage());
            }
        }

        $this->params['search_credit_note_no'] = $searchValue;
        $this->params['search_reference_no'] = $searchValue;

        return " AND (credit_note_no LIKE :search_credit_note_no OR reference_no LIKE :search_reference_no)";
    }

    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $this->relatedDataCache['customers'] = [];
        $ids = array_values(array_unique(array_map(fn($r) => (int)($r['customer_id'] ?? 0), $rows)));
        $ids = array_filter($ids);
        if (!$ids) {
            return;
        }
        $placeholders = [];
        $params = [];
        foreach ($ids as $i => $v) {
            $key = 'cid_' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $v;
        }
        $params['cn_org'] = (int)$this->organizationId;
        try {
            $lookupRows = $this->db->fetchAll(
                "SELECT id, display_name FROM `" . DB::CUSTOMERS . "` WHERE id IN (" . implode(',', $placeholders) . ") AND organization_id = :cn_org",
                $params
            );
            foreach ($lookupRows as $row) {
                $this->relatedDataCache['customers'][(int)$row['id']] = $row['display_name'];
            }
        } catch (\Throwable $e) {
            ErrorCapture::record("CreditNotesDataTable::prepareRelatedData error: " . $e->getMessage());
        }
    }

    protected function formatRow($row, $requestData = [])
    {
        $id       = (int)($row['id'] ?? 0);
        $date     = (string)($row['credit_note_date'] ?? '');
        $no       = (string)($row['credit_note_no'] ?? '');
        $ref      = (string)($row['reference_no'] ?? '');
        $custId   = (int)($row['customer_id'] ?? 0);
        $status   = (string)($row['credit_note_status'] ?? '');
        $total    = (string)($row['grand_total'] ?? '0');
        $custName = $this->relatedDataCache['customers'][$custId] ?? '';

        if ($date === '' || $date === '1970-01-01' || $date === '0000-00-00') {
            $formattedDate = '-';
        } else {
            $ts = strtotime($date);
            $formattedDate = $ts ? date('d M Y', $ts) : $date;
        }

        $badge = $this->statusBadge($status);

        $currencyCode = defined('BASE_CURRENCY') ? BASE_CURRENCY['code'] : 'AED';

        return [
            htmlspecialchars($formattedDate),
            htmlspecialchars($no),
            htmlspecialchars($ref),
            htmlspecialchars($custName),
            $badge,
            $currencyCode . ' ' . number_format((float)$total, 2),
            $id,
            hash('sha512', 'bushogai' . $id),
        ];
    }

    private function statusBadge(string $status): string
    {
        $class = 'bg-secondary';
        switch ($status) {
            case 'open':
            case 'paid':
                $class = 'bg-success';
                break;
            case 'void':
                $class = 'bg-danger';
                break;
            case 'sent':
            case 'approved':
                $class = 'bg-info';
                break;
            case 'not_confirmed':
                $class = 'bg-warning text-dark';
                break;
            default:
                $class = 'bg-secondary';
        }
        return '<span class="badge ' . $class . '">' . htmlspecialchars(ucwords(str_replace('_', ' ', $status))) . '</span>';
    }
}
