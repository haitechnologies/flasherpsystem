<?php

/**
 * HSCodesDataTable Handler
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class HSCodesDataTable extends BaseDataTable
{
    protected $table = DB::HS_CODES;
    protected $searchFields = ['h.code', 'h.old_code', 'te.description', 'ta.description'];
    protected $sortableColumns = [
        0 => 'h.id',            // ID
        1 => 'h.code',          // HS CODE
        2 => 'h.old_code',      // OLD CODE
        3 => 'te.description',  // DESCRIPTION (EN)
        4 => 'ta.description',  // DESCRIPTION (AR)
        5 => 'h.level',         // LEVEL
        6 => 'h.views',         // VIEWS
        7 => 'h.duty_rate'      // DUTY %
    ];
    private $hasTextTable = null;

    protected function buildBaseQuery($requestData)
    {
         $this->ensureTables();

        if ($this->hasTextTable()) {
            return "SELECT h.id, h.code, h.old_code, 
                      IFNULL(te.description, '') as desc_en, '' as short_en,
                      IFNULL(ta.description, '') as desc_ar, '' as short_ar,
                      h.level, h.views, h.duty_rate, h.is_active
                  FROM `" . $this->table . "` h
                  LEFT JOIN " . DB::HS_CODE_TEXTS . " te ON h.id = te.hs_code_id AND te.lang = 'en'
                  LEFT JOIN " . DB::HS_CODE_TEXTS . " ta ON h.id = ta.hs_code_id AND ta.lang = 'ar'
                  WHERE h.id > 0";
        }

         return "SELECT h.id, h.code, h.old_code, 
                  '' as desc_en, '' as short_en,
                  '' as desc_ar, '' as short_ar,
                  h.level, h.views, h.duty_rate, h.is_active
              FROM `" . $this->table . "` h
              WHERE h.id > 0";
    }

    protected function buildSearchClause($requestData)
    {
        $searchValue = $requestData['search']['value'] ?? '';
        if (empty($searchValue)) {
            return '';
        }

        $fields = $this->hasTextTable()
            ? ['h.code', 'h.old_code', 'te.description', 'ta.description']
            : ['h.code', 'h.old_code'];

        $searchVal = '%' . $searchValue . '%';
        $conditions = [];
        foreach ($fields as $i => $field) {
            $key = 'search_val_' . $i;
            $conditions[] = "{$field} LIKE :{$key}";
            $this->params[$key] = $searchVal;
        }

        return 'AND (' . implode(' OR ', $conditions) . ')';
    }

    protected function buildOrderClause($requestData)
    {
        $orderColumn = isset($requestData['order'][0]['column']) ? (int)$requestData['order'][0]['column'] : 1;
        $orderDir = isset($requestData['order'][0]['dir']) ? strtoupper($requestData['order'][0]['dir']) : 'ASC';

        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'ASC';
        }

        $sortableColumns = $this->sortableColumns;
        if (!$this->hasTextTable()) {
            $sortableColumns = [
                0 => 'h.id',
                1 => 'h.code',
                2 => 'h.old_code',
                3 => 'h.code',
                4 => 'h.code',
                5 => 'h.level',
                6 => 'h.duty_rate',
                7 => 'h.id'
            ];
        }

        $column = $sortableColumns[$orderColumn] ?? 'h.code';
        return 'ORDER BY ' . $column . ' ' . $orderDir;
    }

    protected function formatRow($row, $requestData = [])
    {
        try {
            $id = (int)($row['id'] ?? 0);
            $code = isset($row['code']) ? (string)$row['code'] : '';
            $oldCode = isset($row['old_code']) ? (string)$row['old_code'] : '';

            // Clean UTF-8 encoding issues
            $descEn = $this->cleanUTF8((string)($row['desc_en'] ?? ''));
            $descAr = $this->cleanUTF8((string)($row['desc_ar'] ?? ''));

            $level = isset($row['level']) ? (int)$row['level'] : 0;
            $views = isset($row['views']) ? (int)$row['views'] : 0;
            $dutyRate = isset($row['duty_rate']) ? (float)$row['duty_rate'] : 0;
            $isActive = (int)($row['is_active'] ?? 1);

            // Use mb_strlen and mb_substr to properly handle multi-byte UTF-8 characters

            // Show full text in modal (no truncation)
            if (!empty($descEn)) {
                $descEnDisplay = $descEn;
            } else {
                $descEnDisplay = '<span class="text-muted">No description</span>';
            }

            if (!empty($descAr)) {
                $descArDisplay = $descAr;
            } else {
                $descArDisplay = '<span class="text-muted">Ù„Ø§ ÙŠÙˆØ¬Ø¯ ÙˆØµÙ</span>';
            }

            $oldCodeDisplay = !empty($oldCode) ? $oldCode : '<span class="text-muted">-</span>';
            $dutyDisplay = $dutyRate > 0 ? round($dutyRate, 2) . '%' : '<span class="text-muted">0%</span>';

            // Return sequential array (not associative with numeric keys)
            return [
                $this->rowNumber,                                                    // ID (position 0)
                '<strong>' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</strong>',     // HS CODE (position 1)
                $oldCodeDisplay,                                        // OLD CODE (position 2)
                $descEnDisplay,                                         // DESCRIPTION (EN) (position 3)
                $descArDisplay,                                         // DESCRIPTION (AR) (position 4)
                'Level ' . htmlspecialchars((string)$level, ENT_QUOTES, 'UTF-8'),           // LEVEL (position 5)
                number_format($views),                                  // VIEWS (position 6) as plain text
                $dutyDisplay,                                           // DUTY % (position 7)
                $this->getActionButtons(
                    [
                        'id' => $id,
                        'code' => $code,
                        'old_code' => $oldCode,
                        'desc_en' => $descEn,
                        'desc_ar' => $descAr,
                        'level' => $level,
                        'duty_rate' => $dutyRate,
                        'is_active' => $isActive
                    ],
                    'hscodes'
                )                                                       // ACTIONS (position 8)
            ];
        } catch (\Exception $e) {
            ErrorCapture::record('[HSCodesDataTable] Error formatting row: ' . $e->getMessage());
            return [0, 'ERROR', '', 'Error formatting row', '', '', '', ''];
        }
    }

    /**
     * Clean UTF-8 encoding issues in text
     * Handles corrupted multi-byte characters from legacy database with utf8 charset (not utf8mb4)
     */
    private function cleanUTF8($text)
    {
        if (empty($text)) {
            return '';
        }

        $text = (string)$text;

        try {
            // Step 1: Remove control characters and null bytes first
            $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

            // Step 2: Use mb_convert_encoding with auto-detect (using only standard encodings)
            if (function_exists('mb_convert_encoding')) {
                try {
                    $detected = @mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'ASCII'], true);
                    if ($detected && $detected !== 'UTF-8') {
                        $text = @mb_convert_encoding($text, 'UTF-8', $detected);
                    }
                } catch (\Exception $e) {
                    // If detection fails, continue without it
                }
            }

            // Step 3: Convert to UTF-8 with //IGNORE flag using iconv
            if (function_exists('iconv')) {
                $text = @iconv('UTF-8', 'UTF-8//IGNORE', $text) ?: $text;
            }

            // Step 4: Final validation - truncate if still invalid
            if (function_exists('mb_check_encoding')) {
                $attempts = 0;
                while (strlen($text) > 0 && !mb_check_encoding($text, 'UTF-8') && $attempts < 5) {
                    $text = substr($text, 0, -1);
                    $attempts++;
                }
            }
        } catch (\Exception $e) {
            // If all else fails, return empty to avoid breaking JSON
            ErrorCapture::record('[HSCodesDataTable] UTF-8 cleanup exception: ' . $e->getMessage());
            return '';
        }

        return trim($text);
    }

    protected function getActionButtons(array $rowData, $module)
    {
        $id = (int)($rowData['id'] ?? 0);
        $code = (string)($rowData['code'] ?? '');
        $oldCode = (string)($rowData['old_code'] ?? '');
        $descEn = (string)($rowData['desc_en'] ?? '');
        $descAr = (string)($rowData['desc_ar'] ?? '');
        $level = (int)($rowData['level'] ?? 0);
        $dutyRate = (float)($rowData['duty_rate'] ?? 0);
        $isActive = (int)($rowData['is_active'] ?? 0);

        $actions = '';
        if (!empty($code)) {
            $actions .= ActionButtonHelper::publicLinkButton('/trade/hs-code/' . rawurlencode($code), 'Open Public HS Code Page') . ' ';
            $actions .= ActionButtonHelper::ampLinkButton('/trade/hs-code/' . rawurlencode($code) . '/amp', 'Open HS Code AMP Page') . ' ';
        }
        $actions .= '<a href="#" class="view-hscode-btn" '
            . 'data-id="' . $id . '" '
            . 'data-code="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '" '
            . 'data-old-code="' . htmlspecialchars($oldCode, ENT_QUOTES, 'UTF-8') . '" '
            . 'data-desc-en="' . htmlspecialchars($descEn, ENT_QUOTES, 'UTF-8') . '" '
            . 'data-desc-ar="' . htmlspecialchars($descAr, ENT_QUOTES, 'UTF-8') . '" '
            . 'data-level="' . $level . '" '
            . 'data-duty-rate="' . $dutyRate . '" '
            . 'data-is-active="' . $isActive . '" '
            . 'title="View"><i class="ph-eye"></i></a> ';

        // Note: Edit functionality not yet implemented for HS codes
        // if ($this->isGranted('edit', $module)) {
        //     $actions .= '<a href="hscodes.php?action=edit_hscodes&id=' . $id . '"><i class="ph-pencil"></i></a> ';
        // }

        // Delete action intentionally hidden for HS codes listing

        return $actions;
    }

    private function ensureTables(): void
    {
        if (!$this->tableExists($this->table)) {
            ErrorCapture::record('[HSCodesDataTable] CRITICAL: Missing table: ' . $this->table);
            throw new \Exception('Missing table: ' . $this->table);
        }
    }

    private function hasTextTable(): bool
    {
        if ($this->hasTextTable !== null) {
            return $this->hasTextTable;
        }

        $this->hasTextTable = $this->tableExists(DB::HS_CODE_TEXTS);
        if (!$this->hasTextTable) {
            ErrorCapture::record('[HSCodesDataTable] WARNING: HS_CODE_TEXTS table not found, using main table only');
        }
        return $this->hasTextTable;
    }

    private function tableExists(string $tableName): bool
    {
        static $cache = [];
        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }

        try {
            $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name LIMIT 1";
            $row = $this->db->fetchOne($sql, ['table_name' => $tableName]);
            $cache[$tableName] = ($row !== null);
        } catch (\Throwable $e) {
            $cache[$tableName] = false;
        }

        return $cache[$tableName];
    }
}
