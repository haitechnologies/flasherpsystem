<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\PaymentReceived;
use App\Model\PaymentReceivedItem;

/**
 * PaymentReceived Repository
 *
 * Handles PDO-based data access for erp_payments_received and
 * erp_payment_received_items tables with strict tenant isolation.
 */
class PaymentReceivedRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Find payment by ID and organization
     */
    public function find(int $id, int $orgId): ?PaymentReceived
    {
        $sql = "SELECT * FROM `{DB::PAYMENTS_RECEIVED}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToPaymentReceived($row);
    }

    /**
     * Find payment items by payment ID and organization
     */
    public function findItemsByPayment(int $paymentId, int $orgId): array
    {
        $sql = "SELECT * FROM `{DB::PAYMENT_RECEIVED_ITEMS}` WHERE payment_id = :payment_id AND organization_id = :org_id ORDER BY id ASC";
        $rows = $this->db->fetchAll($sql, ['payment_id' => $paymentId, 'org_id' => $orgId]);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapRowToPaymentReceivedItem($row);
        }
        return $items;
    }

    /**
     * Find all payments in an organization
     */
    public function findAll(int $orgId): array
    {
        $sql = "SELECT * FROM `{DB::PAYMENTS_RECEIVED}` WHERE organization_id = :org_id ORDER BY id DESC";
        $rows = $this->db->fetchAll($sql, ['org_id' => $orgId]);
        $payments = [];
        foreach ($rows as $row) {
            $payments[] = $this->mapRowToPaymentReceived($row);
        }
        return $payments;
    }

    /**
     * Get the last payment number for a given monthly prefix and organization
     */
    public function getLastPaymentNoForMonth(string $prefix, int $orgId): ?string
    {
        $sql = "SELECT payment_no FROM `{DB::PAYMENTS_RECEIVED}`
                WHERE payment_no LIKE :prefix AND organization_id = :org_id
                ORDER BY payment_no DESC LIMIT 1";
        $row = $this->db->fetchOne($sql, ['prefix' => $prefix . '%', 'org_id' => $orgId]);
        return $row !== null ? (string)$row['payment_no'] : null;
    }

    /**
     * Save Payment (Insert or Update)
     */
    public function save(PaymentReceived $payment): PaymentReceived
    {
        if ($payment->id === null) {
            return $this->insert($payment);
        }
        return $this->update($payment);
    }

    private function insert(PaymentReceived $payment): PaymentReceived
    {
        $sql = "INSERT INTO `{DB::PAYMENTS_RECEIVED}` (
                    organization_id, payment_no, payment_status, customer_id, total_amount_received,
                    bank_charges, payment_date, payment_method, deposit_to, reference_no,
                    publish, is_active, created_at, updated_at, updated_by, created_by
                ) VALUES (
                    :organization_id, :payment_no, :payment_status, :customer_id, :total_amount_received,
                    :bank_charges, :payment_date, :payment_method, :deposit_to, :reference_no,
                    :publish, :is_active, NOW(), NOW(), :updated_by, :created_by
                )";

        $params = $payment->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at']);

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->find($insertId, $payment->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted payment.");
        }

        return $inserted;
    }

    private function update(PaymentReceived $payment): PaymentReceived
    {
        $sql = "UPDATE `{DB::PAYMENTS_RECEIVED}` SET
                    payment_no = :payment_no,
                    payment_status = :payment_status,
                    customer_id = :customer_id,
                    total_amount_received = :total_amount_received,
                    bank_charges = :bank_charges,
                    payment_date = :payment_date,
                    payment_method = :payment_method,
                    deposit_to = :deposit_to,
                    reference_no = :reference_no,
                    publish = :publish,
                    is_active = :is_active,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id AND organization_id = :organization_id";

        $params = $payment->toArray();
        unset($params['created_at'], $params['updated_at'], $params['created_by']);

        $this->db->execute($sql, $params);

        $updated = $this->find((int)$payment->id, $payment->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated payment.");
        }

        return $updated;
    }

    /**
     * Save PaymentReceivedItem (Insert or Update)
     */
    public function saveItem(PaymentReceivedItem $item): PaymentReceivedItem
    {
        if ($item->id === null) {
            return $this->insertItem($item);
        }
        return $this->updateItem($item);
    }

    private function insertItem(PaymentReceivedItem $item): PaymentReceivedItem
    {
        $sql = "INSERT INTO `{DB::PAYMENT_RECEIVED_ITEMS}` (
                    payment_id, organization_id, invoice_id, amount_received_on, amount_received,
                    created_at, updated_at, updated_by, created_by
                ) VALUES (
                    :payment_id, :organization_id, :invoice_id, :amount_received_on, :amount_received,
                    NOW(), NOW(), :updated_by, :created_by
                )";

        $params = $item->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at']);

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->findItem($insertId, $item->paymentId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted payment item.");
        }

        return $inserted;
    }

    private function updateItem(PaymentReceivedItem $item): PaymentReceivedItem
    {
        $sql = "UPDATE `{DB::PAYMENT_RECEIVED_ITEMS}` SET
                    invoice_id = :invoice_id,
                    amount_received_on = :amount_received_on,
                    amount_received = :amount_received,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id AND payment_id = :payment_id";

        $params = $item->toArray();
        unset($params['created_at'], $params['updated_at'], $params['created_by']);

        $this->db->execute($sql, $params);

        $updated = $this->findItem((int)$item->id, $item->paymentId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated payment item.");
        }

        return $updated;
    }

    /**
     * Find a single PaymentReceivedItem by ID
     */
    public function findItem(int $id, int $paymentId): ?PaymentReceivedItem
    {
        $sql = "SELECT * FROM `{DB::PAYMENT_RECEIVED_ITEMS}` WHERE id = :id AND payment_id = :payment_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'payment_id' => $paymentId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToPaymentReceivedItem($row);
    }

    /**
     * Delete payment items by IDs (for update reconciliation)
     */
    public function deleteItemsByIds(array $ids, int $paymentId, int $orgId): void
    {
        if (empty($ids)) {
            return;
        }
        $placeholders = [];
        $params = ['payment_id' => $paymentId, 'org_id' => $orgId];
        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$id;
        }
        $placeholdersStr = implode(',', $placeholders);

        $sql = "DELETE FROM `{DB::PAYMENT_RECEIVED_ITEMS}`
                WHERE payment_id = :payment_id AND organization_id = :org_id AND id IN ({$placeholdersStr})";
        $this->db->execute($sql, $params);
    }

    /**
     * Delete a payment (cascades items) within a transaction
     */
    public function delete(int $id, int $orgId): bool
    {
        $sqlItems = "DELETE FROM `{DB::PAYMENT_RECEIVED_ITEMS}` WHERE payment_id = :id AND organization_id = :org_id";
        $this->db->execute($sqlItems, ['id' => $id, 'org_id' => $orgId]);

        $sql = "DELETE FROM `{DB::PAYMENTS_RECEIVED}` WHERE id = :id AND organization_id = :org_id";
        $this->db->execute($sql, ['id' => $id, 'org_id' => $orgId]);

        return true;
    }

    /**
     * Update payment status
     */
    public function updateStatus(int $id, string $status, int $orgId): bool
    {
        $sql = "UPDATE `{DB::PAYMENTS_RECEIVED}` SET payment_status = :status, updated_at = NOW()
                WHERE id = :id AND organization_id = :org_id";
        $this->db->execute($sql, ['status' => $status, 'id' => $id, 'org_id' => $orgId]);
        return true;
    }

    /**
     * Map row to PaymentReceived DTO
     */
    private function mapRowToPaymentReceived(array $row): PaymentReceived
    {
        return new PaymentReceived(
            id: isset($row['id']) ? (int)$row['id'] : null,
            organizationId: (int)$row['organization_id'],
            paymentNo: $row['payment_no'] ?? null,
            paymentStatus: (string)($row['payment_status'] ?? 'draft'),
            customerId: (int)($row['customer_id'] ?? 0),
            totalAmountReceived: (float)($row['total_amount_received'] ?? 0.0),
            bankCharges: (float)($row['bank_charges'] ?? 0.0),
            paymentDate: (string)($row['payment_date'] ?? '1970-01-01'),
            paymentMethod: $row['payment_method'] !== null ? (int)$row['payment_method'] : null,
            depositTo: (int)($row['deposit_to'] ?? 0),
            referenceNo: $row['reference_no'] ?? null,
            publish: (bool)($row['publish'] ?? 1),
            isActive: (bool)($row['is_active'] ?? 1),
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
            updatedBy: $row['updated_by'] !== null ? (int)$row['updated_by'] : null,
            createdBy: (int)($row['created_by'] ?? 0)
        );
    }

    /**
     * Map row to PaymentReceivedItem DTO
     */
    private function mapRowToPaymentReceivedItem(array $row): PaymentReceivedItem
    {
        return new PaymentReceivedItem(
            id: isset($row['id']) ? (int)$row['id'] : null,
            paymentId: (int)($row['payment_id'] ?? 0),
            invoiceId: (int)($row['invoice_id'] ?? 0),
            amountReceivedOn: (string)($row['amount_received_on'] ?? '1970-01-01'),
            amountReceived: (float)($row['amount_received'] ?? 0.0),
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
            updatedBy: $row['updated_by'] !== null ? (int)$row['updated_by'] : null,
            createdBy: (int)($row['created_by'] ?? 0)
        );
    }
}
