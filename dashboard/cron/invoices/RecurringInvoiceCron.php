<?php
require_once dirname(dirname(__DIR__)) . '/admin_elements/error_handler_init.php';

use App\Core\DB;

require_once __DIR__ . '/../../../config/globals.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../CronJobBase.php';

class RecurringInvoiceCron extends CronJobBase {

    protected function getJobName() {
        return 'recurring_invoice';
    }

    public function execute() {
        $this->log("Generating due recurring invoices...", 'INFO');

        $prefix = $GLOBALS['TBL']['PREFIX'];
        $generated = 0;

        // Per-organization scope (recurring invoices are org-scoped)
        $orgSql = "SELECT id FROM `{$prefix}organizations` WHERE is_active = 1 ORDER BY id ASC";
        $orgResult = $this->safeQuery($orgSql);

        if (!$orgResult) {
            $this->log("Could not list organizations.", 'ERROR');
            return;
        }

        while ($org = $orgResult->fetch_assoc()) {
            $orgId = (int)$org['id'];

            // Due recurring profiles: active, not completed, not expired
            $profileSql = "SELECT * FROM `{$prefix}invoices`
                           WHERE organization_id = $orgId
                             AND recurring = 1
                             AND recurring_status = 1
                             AND is_active = 1
                             AND (end_date IS NULL OR end_date = '1970-01-01' OR end_date >= CURDATE())";
            $profiles = $this->safeQuery($profileSql);

            if (!$profiles) {
                continue;
            }

            while ($profile = $profiles->fetch_assoc()) {
                $dueDate = $this->resolveNextDate($profile);

                if ($dueDate === null) {
                    $this->log("Profile #{$profile['id']} has no schedule date; skipping.", 'WARNING');
                    continue;
                }

                // Only generate when the next date has arrived
                if ($dueDate > date('Y-m-d')) {
                    continue;
                }

                $instanceId = $this->generateInvoiceInstance($profile, $dueDate, $orgId);
                if ($instanceId > 0) {
                    $generated++;
                    $this->log("Generated invoice #{$instanceId} from recurring profile #{$profile['id']} (org $orgId, date $dueDate)", 'SUCCESS');
                    $this->advanceProfile($profile, $dueDate);
                } else {
                    $this->incrementErrors();
                    $this->log("Failed to generate invoice from recurring profile #{$profile['id']} (org $orgId)", 'ERROR');
                }
            }
        }

        if ($generated > 0) {
            $this->incrementProcessed($generated);
            $this->log("Generated $generated recurring invoice(s).", 'SUCCESS');
        } else {
            $this->log("No recurring invoices due.", 'INFO');
        }
    }

    /**
     * Resolve the next scheduled invoice date for a recurring profile.
     */
    private function resolveNextDate(array $profile): ?string {
        foreach (['next_invoice_date', 'start_date', 'invoice_date'] as $key) {
            $value = trim((string)($profile[$key] ?? ''));
            if ($value !== '' && $value !== '1970-01-01' && $value !== '0000-00-00') {
                return $value;
            }
        }
        return null;
    }

    /**
     * Create a regular (recurring=0) invoice instance from the profile, copying header + items.
     */
    private function generateInvoiceInstance(array $profile, string $invoiceDate, int $orgId): int {
        $prefix = $GLOBALS['TBL']['PREFIX'];

        $invoiceNo = $this->generateInvoiceNo($orgId);

        $headerColumns = [
            'organization_id'    => $orgId,
            'recurring'          => 0,
            'invoice_no'         => $invoiceNo,
            'customer_id'        => (int)$profile['customer_id'],
            'invoice_status'     => 'draft',
            'invoice_date'       => $invoiceDate,
            'expiry_date'        => $profile['expiry_date'] ?: '1970-01-01',
            'reference_no'       => $profile['reference_no'] ?: '',
            'warehouse_id'       => $profile['warehouse_id'] ?: null,
            'expected_shipment_date' => $profile['expected_shipment_date'] ?: null,
            'payment_term'       => $profile['payment_term'] ?: null,
            'shipment_type'      => $profile['shipment_type'] ?: '',
            'sales_person'       => $profile['sales_person'] ?: null,
            'job_reference_no'   => $profile['job_reference_no'] ?: '',
            'master_awb_no'      => $profile['master_awb_no'] ?: '',
            'shipper'            => $profile['shipper'] ?: null,
            'consignee'          => $profile['consignee'] ?: null,
            'origin'             => $profile['origin'] ?: null,
            'destination'        => $profile['destination'] ?: null,
            'no_of_packs'        => $profile['no_of_packs'] ?: 0,
            'gross_weight'       => $profile['gross_weight'] ?: 0,
            'chargeable_weight'  => $profile['chargeable_weight'] ?: 0,
            'volume'             => $profile['volume'] ?: 0,
            'customer_notes'     => $profile['customer_notes'] ?: '',
            'terms_and_conditions' => $profile['terms_and_conditions'] ?: '',
            'grand_subtotal'     => $profile['grand_subtotal'] ?: 0,
            'grand_discount_type' => $profile['grand_discount_type'] ?: '',
            'grand_discount_type_value' => $profile['grand_discount_type_value'] ?: 0,
            'grand_discount_amount' => $profile['grand_discount_amount'] ?: 0,
            'grand_after_discount' => $profile['grand_after_discount'] ?: 0,
            'grand_tax'          => $profile['grand_tax'] ?: 0,
            'grand_total'        => $profile['grand_total'] ?: 0,
            'profile_name'       => $profile['profile_name'] ?: '',
            'frequency'          => $profile['frequency'] ?: '',
            'start_date'         => $profile['start_date'] ?: null,
            'end_date'           => $profile['end_date'] ?: null,
            'publish'            => (int)($profile['publish'] ?? 1),
            'is_active'          => 1,
            'created_by'         => (int)($profile['created_by'] ?? 0),
            'created_at'         => date('Y-m-d H:i:s'),
        ];

        $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($headerColumns)));
        $vals = implode(', ', array_map(fn($v) => $this->quote($v), array_values($headerColumns)));

        $this->mysqli->begin_transaction();
        try {
            $insertSql = "INSERT INTO `{$prefix}invoices` ($cols) VALUES ($vals)";
            if (!$this->mysqli->query($insertSql)) {
                $this->log("Invoice INSERT failed: " . $this->mysqli->error, 'ERROR');
                $this->mysqli->rollback();
                return 0;
            }
            $invoiceId = (int)$this->mysqli->insert_id;

            // Copy items from profile
            $itemsSql = "SELECT service, description, qty, rate, sub_total, tax, tax_amount, total
                         FROM `{$prefix}invoice_items`
                         WHERE invoice_id = " . (int)$profile['id'] . "
                         ORDER BY id ASC";
            $itemsResult = $this->mysqli->query($itemsSql);
            if ($itemsResult) {
                while ($item = $itemsResult->fetch_assoc()) {
                    $itemCols = 'invoice_id, service, description, qty, rate, sub_total, tax, tax_amount, total, organization_id, created_at';
                    $itemVals = $invoiceId . ', '
                        . $this->quote($item['service']) . ', '
                        . $this->quote($item['description']) . ', '
                        . $this->quote($item['qty']) . ', '
                        . $this->quote($item['rate']) . ', '
                        . $this->quote($item['sub_total']) . ', '
                        . $this->quote($item['tax']) . ', '
                        . $this->quote($item['tax_amount']) . ', '
                        . $this->quote($item['total']) . ', '
                        . $orgId . ', '
                        . $this->quote(date('Y-m-d H:i:s'));
                    $this->mysqli->query("INSERT INTO `{$prefix}invoice_items` ($itemCols) VALUES ($itemVals)");
                }
            }

            $this->mysqli->commit();
            return $invoiceId;
        } catch (\Throwable $e) {
            if ($this->mysqli->connect_errno === 0) {
                $this->mysqli->rollback();
            }
            $this->log("Invoice instance generation threw: " . $e->getMessage(), 'ERROR');
            return 0;
        }
    }

    /**
     * Advance the profile schedule after a successful generation.
     */
    private function advanceProfile(array $profile, string $lastDate): void {
        $prefix = $GLOBALS['TBL']['PREFIX'];
        $profileId = (int)$profile['id'];

        $nextDate = $this->advanceDate($lastDate, (string)$profile['frequency']);

        // Auto-complete when schedule passes end_date
        $endDate = trim((string)($profile['end_date'] ?? ''));
        $completed = false;
        if ($endDate !== '' && $endDate !== '1970-01-01' && $endDate !== '0000-00-00' && $nextDate > $endDate) {
            $completed = true;
        }

        $sql = "UPDATE `{$prefix}invoices`
                SET last_invoice_date = " . $this->quote($lastDate) . ",
                    next_invoice_date = " . $this->quote($nextDate) . ",
                    recurring_status = " . ($completed ? '0' : '1') . ",
                    updated_at = " . $this->quote(date('Y-m-d H:i:s')) . "
                WHERE id = $profileId AND recurring = 1";
        if ($this->safeQuery($sql)) {
            if ($completed) {
                $this->log("Recurring profile #{$profileId} completed (past end_date $endDate).", 'INFO');
            }
        } else {
            $this->log("Failed to advance recurring profile #{$profileId}.", 'ERROR');
            $this->incrementErrors();
        }
    }

    /**
     * Advance a date by a frequency step.
     */
    private function advanceDate(string $date, string $frequency): string {
        switch ($frequency) {
            case 'week':
                $modifier = '+7 days';
                break;
            case '2_weeks':
                $modifier = '+14 days';
                break;
            case '6_months':
                $modifier = '+6 months';
                break;
            case 'year':
                $modifier = '+1 year';
                break;
            case 'month':
            default:
                $modifier = '+1 month';
                break;
        }
        $ts = strtotime($date . ' ' . $modifier);
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d', strtotime('+1 month', strtotime($date)));
    }

    /**
     * Generate the next FL-IN{ym}-NNNN invoice number for an organization.
     */
    private function generateInvoiceNo(int $orgId): string {
        $prefix = $GLOBALS['TBL']['PREFIX'];
        $ym = date('ym');
        $like = 'FL-IN' . $ym . '%';
        $orgPart = $orgId;

        $sql = "SELECT invoice_no FROM `{$prefix}invoices`
                WHERE organization_id = $orgPart AND invoice_no LIKE '" . $this->mysqli->real_escape_string($like) . "'
                ORDER BY id DESC LIMIT 1";
        $result = $this->mysqli->query($sql);
        $serial = 1;
        if ($result && $row = $result->fetch_assoc()) {
            $last = $row['invoice_no'];
            $parts = explode('-', (string)$last);
            $num = (int)end($parts);
            $serial = $num + 1;
        }
        return 'FL-IN' . $ym . '-' . str_pad((string)$serial, 4, '0', STR_PAD_LEFT);
    }

    private function quote($value): string {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        return "'" . $this->mysqli->real_escape_string((string)$value) . "'";
    }
}

if (php_sapi_name() === 'cli') {
    $mysqli = $GLOBALS['DB']['MSQLI'];
    $cron = new RecurringInvoiceCron($mysqli);
    $cron->run();
} else {
    http_response_code(403);
    die('CLI only');
}
