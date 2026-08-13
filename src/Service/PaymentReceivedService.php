<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\DB;
use App\Model\PaymentReceived;
use App\Model\PaymentReceivedItem;
use App\Repository\PaymentReceivedRepository;
use App\Repository\CustomerRepository;
use App\Repository\JournalRepository;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;

/**
 * PaymentReceived Service
 *
 * Implements business logic and validations for customer payments received,
 * including automatic Accounts Receivable / Deposit-To journal entries.
 */
class PaymentReceivedService
{
    private PaymentReceivedRepository $paymentRepo;
    private CustomerRepository $customerRepo;
    private JournalRepository $journalRepo;
    private JournalService $journalService;
    private Database $db;

    public function __construct(
        PaymentReceivedRepository $paymentRepo,
        CustomerRepository $customerRepo,
        JournalRepository $journalRepo,
        JournalService $journalService,
        Database $db
    ) {
        $this->paymentRepo = $paymentRepo;
        $this->customerRepo = $customerRepo;
        $this->journalRepo = $journalRepo;
        $this->journalService = $journalService;
        $this->db = $db;
    }

    /**
     * Get a payment by ID and organization.
     *
     * @throws NotFoundException
     */
    public function getPayment(int $id, int $orgId): PaymentReceived
    {
        $payment = $this->paymentRepo->find($id, $orgId);
        if ($payment === null) {
            throw new NotFoundException("Payment with ID {$id} not found.");
        }
        return $payment;
    }

    /**
     * Get line items of a payment.
     */
    public function getPaymentItems(int $paymentId, int $orgId): array
    {
        return $this->paymentRepo->findItemsByPayment($paymentId, $orgId);
    }

    /**
     * List all payments in an organization.
     */
    public function list(int $orgId): array
    {
        return $this->paymentRepo->findAll($orgId);
    }

    /**
     * Create a new customer payment.
     *
     * @throws ValidationException
     */
    public function createPayment(array $data, array $itemsData, int $orgId, int $userId): PaymentReceived
    {
        $this->validatePaymentData($data, $orgId);
        if (empty($itemsData)) {
            throw new ValidationException(['items' => "No items added. Please add at least one item."]);
        }
        $this->validateItemsTotal($data, $itemsData);

        $this->db->beginTransaction();
        try {
            $paymentNo = $this->generatePaymentNo($orgId);
            $paymentDate = $this->normalizeDate((string)($data['payment_date'] ?? date('Y-m-d')));

            $totalAmountReceived = (float)($data['total_amount_received'] ?? 0.0);
            $paymentStatus = !empty($data['payment_status']) ? trim((string)$data['payment_status']) : 'draft';

            $payment = new PaymentReceived(
                id: null,
                organizationId: $orgId,
                paymentNo: $paymentNo,
                paymentStatus: $paymentStatus,
                customerId: (int)$data['customer_id'],
                totalAmountReceived: $totalAmountReceived,
                bankCharges: !empty($data['bank_charges']) ? (float)$data['bank_charges'] : 0.0,
                paymentDate: $paymentDate,
                paymentMethod: !empty($data['payment_method']) ? (int)$data['payment_method'] : null,
                depositTo: (int)($data['deposit_to'] ?? 0),
                referenceNo: !empty($data['reference_no']) ? trim((string)$data['reference_no']) : null,
                publish: isset($data['publish']) ? (bool)$data['publish'] : true,
                isActive: isset($data['is_active']) ? (bool)$data['is_active'] : true,
                createdBy: $userId,
            );

            $savedPayment = $this->paymentRepo->save($payment);
            $paymentId = $savedPayment->id;
            if ($paymentId === null) {
                throw new \RuntimeException("Failed to insert payment header.");
            }

            $this->saveItems($paymentId, $orgId, $itemsData, $userId);

            if ($savedPayment->paymentStatus === 'paid' && $savedPayment->totalAmountReceived > 0) {
                $this->createPaymentJournal($savedPayment, $orgId, $userId, 'payment_received', false);
            }

            $this->db->commit();
            return $savedPayment;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing customer payment.
     *
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function updatePayment(int $id, array $data, array $itemsData, int $orgId, int $userId): PaymentReceived
    {
        $payment = $this->getPayment($id, $orgId);
        $this->validatePaymentData($data, $orgId);
        $this->validateItemsTotal($data, $itemsData);

        $this->db->beginTransaction();
        try {
            $paymentDate = isset($data['payment_date']) ? $this->normalizeDate((string)$data['payment_date']) : $payment->paymentDate;

            $totalAmountReceived = isset($data['total_amount_received']) ? (float)$data['total_amount_received'] : $payment->totalAmountReceived;
            $paymentStatus = isset($data['payment_status']) ? trim((string)$data['payment_status']) : $payment->paymentStatus;

            $updatedPayment = new PaymentReceived(
                id: $payment->id,
                organizationId: $payment->organizationId,
                paymentNo: isset($data['payment_no']) ? trim((string)$data['payment_no']) : $payment->paymentNo,
                paymentStatus: $paymentStatus,
                customerId: isset($data['customer_id']) ? (int)$data['customer_id'] : $payment->customerId,
                totalAmountReceived: $totalAmountReceived,
                bankCharges: isset($data['bank_charges']) ? (float)$data['bank_charges'] : $payment->bankCharges,
                paymentDate: $paymentDate,
                paymentMethod: isset($data['payment_method']) ? (!empty($data['payment_method']) ? (int)$data['payment_method'] : null) : $payment->paymentMethod,
                depositTo: isset($data['deposit_to']) ? (int)$data['deposit_to'] : $payment->depositTo,
                referenceNo: isset($data['reference_no']) ? (!empty($data['reference_no']) ? trim((string)$data['reference_no']) : null) : $payment->referenceNo,
                publish: isset($data['publish']) ? (bool)$data['publish'] : $payment->publish,
                isActive: isset($data['is_active']) ? (bool)$data['is_active'] : $payment->isActive,
                createdAt: $payment->createdAt,
                createdBy: $payment->createdBy,
                updatedBy: $userId,
            );

            $savedPayment = $this->paymentRepo->save($updatedPayment);

            // Reconcile items: form rows carry invoice_id (not item PK), so diffing
            // by id is meaningless. Delete all existing item rows, then re-insert
            // the submitted ones (matches the form's invoice-scoped rows).
            $existingItems = $this->paymentRepo->findItemsByPayment($id, $orgId);
            if (!empty($existingItems)) {
                $existingIds = array_map(fn($item) => $item->id, $existingItems);
                $this->paymentRepo->deleteItemsByIds($existingIds, $id, $orgId);
            }

            foreach ($itemsData as $itemData) {
                if (empty($itemData['invoice_id']) || empty($itemData['amount_received'])) {
                    continue;
                }
                $this->saveItem($id, $orgId, $itemData, $userId);
            }

            // Recreate journal when paid: remove old journals, re-issue if still paid
            $this->deletePaymentJournals($id, $orgId);
            if ($savedPayment->paymentStatus === 'paid' && $savedPayment->totalAmountReceived > 0) {
                $this->createPaymentJournal($savedPayment, $orgId, $userId, 'payment_received', false);
            }

            $this->db->commit();
            return $savedPayment;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Delete a payment, its line items, and its journal entries.
     */
    public function deletePayment(int $id, int $orgId): bool
    {
        $this->getPayment($id, $orgId);

        $this->db->beginTransaction();
        try {
            $this->deletePaymentJournals($id, $orgId);
            $result = $this->paymentRepo->delete($id, $orgId);
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Clone a payment as a new draft.
     */
    public function clonePayment(int $id, int $orgId, int $userId): PaymentReceived
    {
        $payment = $this->getPayment($id, $orgId);
        $items = $this->getPaymentItems($id, $orgId);

        $this->db->beginTransaction();
        try {
            $paymentNo = $this->generatePaymentNo($orgId);

            $cloned = new PaymentReceived(
                id: null,
                organizationId: $orgId,
                paymentNo: $paymentNo,
                paymentStatus: 'draft',
                customerId: $payment->customerId,
                totalAmountReceived: $payment->totalAmountReceived,
                bankCharges: $payment->bankCharges,
                paymentDate: date('Y-m-d'),
                paymentMethod: $payment->paymentMethod,
                depositTo: $payment->depositTo,
                referenceNo: $payment->referenceNo,
                publish: $payment->publish,
                isActive: $payment->isActive,
                createdBy: $userId,
            );

            $savedCloned = $this->paymentRepo->save($cloned);
            $newPaymentId = $savedCloned->id;
            if ($newPaymentId === null) {
                throw new \RuntimeException("Failed to clone payment header.");
            }

            foreach ($items as $item) {
                $clonedItem = new PaymentReceivedItem(
                    id: null,
                    paymentId: $newPaymentId,
                    organizationId: $orgId,
                    invoiceId: $item->invoiceId,
                    amountReceivedOn: $item->amountReceivedOn,
                    amountReceived: $item->amountReceived,
                    createdBy: $userId,
                );
                $this->paymentRepo->saveItem($clonedItem);
            }

            $this->db->commit();
            return $savedCloned;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Void a payment: issue a reversing journal and set status to 'void'.
     */
    public function voidPayment(int $id, int $orgId, int $userId): PaymentReceived
    {
        $payment = $this->getPayment($id, $orgId);

        $this->db->beginTransaction();
        try {
            $this->deletePaymentJournals($id, $orgId);
            if ($payment->paymentStatus === 'paid' && $payment->totalAmountReceived > 0) {
                $this->createPaymentJournal($payment, $orgId, $userId, 'payment_received_void', true);
            }
            $this->paymentRepo->updateStatus($id, 'void', $orgId);
            $this->db->commit();

            return $this->getPayment($id, $orgId);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update payment status.
     */
    public function updateStatus(int $id, string $status, int $orgId): bool
    {
        $allowed = ['draft', 'paid', 'void', 'refund'];
        if (!in_array($status, $allowed, true)) {
            throw new ValidationException(['status' => "Invalid status: {$status}"]);
        }
        return $this->paymentRepo->updateStatus($id, $status, $orgId);
    }

    /**
     * Save line items for a payment (insert path).
     */
    private function saveItems(int $paymentId, int $orgId, array $itemsData, int $userId): void
    {
        foreach ($itemsData as $itemData) {
            $this->saveItem($paymentId, $orgId, $itemData, $userId);
        }
    }

    private function saveItem(int $paymentId, int $orgId, array $itemData, int $userId): void
    {
        if (empty($itemData['invoice_id']) || empty($itemData['amount_received'])) {
            return;
        }
        $item = new PaymentReceivedItem(
            id: null,
            paymentId: $paymentId,
            organizationId: $orgId,
            invoiceId: (int)$itemData['invoice_id'],
            amountReceivedOn: $this->normalizeDate((string)($itemData['amount_received_on'] ?? date('Y-m-d'))),
            amountReceived: (float)$itemData['amount_received'],
            createdBy: $userId,
        );
        $this->paymentRepo->saveItem($item);
    }

    /**
     * Generate the next sequential payment number for the current month.
     */
    private function generatePaymentNo(int $orgId): string
    {
        $prefix = 'PAY' . date('ym');
        $lastNo = $this->paymentRepo->getLastPaymentNoForMonth($prefix, $orgId);
        if ($lastNo === null) {
            $serial = 1;
        } else {
            $serial = (int)substr($lastNo, -4) + 1;
        }
        return $prefix . '-' . str_pad((string)$serial, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Delete all journal entries referencing this payment.
     */
    private function deletePaymentJournals(int $paymentId, int $orgId): void
    {
        foreach (['payment_received', 'payment_received_void', 'payment_received_refund'] as $refType) {
            $journals = $this->journalRepo->findByReference($refType, $paymentId, $orgId);
            foreach ($journals as $journal) {
                if ($journal->id !== null) {
                    $this->journalRepo->delete((int)$journal->id, $orgId);
                }
            }
        }
    }

    /**
     * Create the AR / Deposit-To journal entry for a payment.
     */
    private function createPaymentJournal(PaymentReceived $payment, int $orgId, int $userId, string $referenceType, bool $reverse): void
    {
        $total = $payment->totalAmountReceived;
        $bankCharges = $payment->bankCharges > 0 ? $payment->bankCharges : 0.0;
        $arId = $this->findAccountsReceivableId();
        $depositTo = $payment->depositTo;
        $bankChargesAccountId = $this->findBankChargesAccountId();

        $customerName = '';
        $customer = $this->customerRepo->find($payment->customerId, $orgId);
        if ($customer !== null) {
            $customerName = $customer->displayName;
        }

        if ($reverse) {
            $itemsData = [
                ['account' => $arId, 'debit' => $total, 'credit' => 0.0, 'description' => 'Payment Voided'],
                ['account' => $depositTo, 'debit' => 0.0, 'credit' => $total, 'description' => 'Payment Voided'],
            ];
            $notes = 'Payment Voided #' . $payment->id . ' - ' . $customerName;
        } else {
            if ($bankCharges > 0 && $bankChargesAccountId > 0 && $bankChargesAccountId !== $depositTo) {
                // Split deposit: net amount to deposit account, bank charges to expense account.
                $itemsData = [
                    ['account' => $depositTo, 'debit' => $total - $bankCharges, 'credit' => 0.0, 'description' => 'Payment Received'],
                    ['account' => $bankChargesAccountId, 'debit' => $bankCharges, 'credit' => 0.0, 'description' => 'Bank Charges'],
                    ['account' => $arId, 'debit' => 0.0, 'credit' => $total, 'description' => 'Payment Received'],
                ];
            } else {
                $itemsData = [
                    ['account' => $depositTo, 'debit' => $total, 'credit' => 0.0, 'description' => 'Payment Received'],
                    ['account' => $arId, 'debit' => 0.0, 'credit' => $total, 'description' => 'Payment Received'],
                ];
            }
            $notes = 'Payment Received #' . $payment->id . ' - ' . $customerName;
        }

        $this->journalService->createJournal(
            [
                'journal_date' => $payment->paymentDate,
                'journal_status' => 'posted',
                'reference_no' => $payment->paymentNo,
                'notes' => $notes,
                'reporting_method' => 'cash',
                'reference_type' => $referenceType,
                'reference_id' => $payment->id,
                'currency' => 'AED',
                'warehouse_id' => 0,
            ],
            $itemsData,
            $orgId,
            $userId
        );
    }

    /**
     * Look up a bank charges expense account ID dynamically (best effort).
     * Returns 0 when no suitable account exists, so callers can fall back.
     */
    private function findBankChargesAccountId(): int
    {
        $sql = "SELECT id FROM `{DB::ACCOUNTS}`
                WHERE account_name LIKE '%Bank Charge%' OR account_name LIKE '%Bank Charges%'
                   OR account_code IN ('6400','6401','6402')
                LIMIT 1";
        $row = $this->db->fetchOne($sql);
        return $row !== null ? (int)$row['id'] : 0;
    }

    /**
     * Look up the Accounts Receivable account ID dynamically.
     */
    private function findAccountsReceivableId(): int
    {
        $sql = "SELECT id FROM `{DB::ACCOUNTS}`
                WHERE account_code IN ('1200','1210','1100') OR account_name LIKE '%Receivable%'
                LIMIT 1";
        $row = $this->db->fetchOne($sql);
        return $row !== null ? (int)$row['id'] : 0;
    }

    /**
     * Validate payment header fields.
     *
     * @throws ValidationException
     */
    private function validatePaymentData(array $data, int $orgId): void
    {
        if (empty($data['customer_id']) || $data['customer_id'] === 'Please select') {
            throw new ValidationException(['customer_id' => "Please select Customer."]);
        }
        if (empty($data['payment_date'])) {
            throw new ValidationException(['payment_date' => "Please select Payment Date."]);
        }
        if (empty($data['deposit_to'])) {
            throw new ValidationException(['deposit_to' => "Please select Deposit To account."]);
        }

        $customer = $this->customerRepo->find((int)$data['customer_id'], $orgId);
        if ($customer === null) {
            throw new ValidationException(['customer_id' => "Selected customer does not exist in your organization."]);
        }

        $allowedStatuses = ['draft', 'paid', 'void', 'refund'];
        $status = isset($data['payment_status']) ? trim((string)$data['payment_status']) : 'draft';
        if (!in_array($status, $allowedStatuses, true)) {
            throw new ValidationException(['payment_status' => "Invalid status: {$status}"]);
        }
    }

    /**
     * Verify the client-submitted total matches the sum of item allocations.
     */
    private function validateItemsTotal(array $data, array $itemsData): void
    {
        $submittedTotal = (float)($data['total_amount_received'] ?? 0.0);
        $itemsTotal = 0.0;
        foreach ($itemsData as $item) {
            $itemsTotal += (float)($item['amount_received'] ?? 0.0);
        }
        if (abs($itemsTotal - $submittedTotal) > 0.01) {
            throw new ValidationException([
                'total_amount_received' => "The total received amount (" . number_format($submittedTotal, 2) . ") does not match the sum of item allocations (" . number_format($itemsTotal, 2) . ").",
            ]);
        }
    }

    /**
     * Normalize a date from display/input formats (d-m-Y, d/m/Y) to DB format (Y-m-d).
     * Already-normalized Y-m-d values pass through unchanged.
     */
    private function normalizeDate(string $date): string
    {
        $date = trim($date);
        if ($date === '' || $date === '1970-01-01') {
            return $date;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        $converted = \App\Helper\DateHelper::toDbDate($date);
        return $converted !== '' ? $converted : $date;
    }
}
