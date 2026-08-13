<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\PaymentReceivedService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;
use App\Helper\DateHelper;
use App\Helper\PdfGeneratorHelper;

class PaymentReceivedController extends BaseController
{
    private PaymentReceivedService $paymentService;

    public function __construct(
        Database $db,
        PaymentReceivedService $paymentService,
        int $userId = 0,
        int $roleId = 0,
        int $orgId = 0,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->paymentService = $paymentService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('payments_received', 'Payment Received');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_payments_received' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_payments_received' && $this->canCreate()
                => $this->handleCreate($request),
            $request->isPost() && $action === 'delete_payments_received' && $id > 0 && $this->canDelete()
                => $this->handleDelete($request, $id),
            $request->isPost() && $action === 'clone_payments_received' && $id > 0 && $this->canCreate()
                => $this->handleClone($request, $id),
            $request->isPost() && $action === 'void_payments_received' && $id > 0 && $this->canEdit()
                => $this->handleVoid($request, $id),
            default => $this->showForm($request, $id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $payment = $this->paymentService->getPayment($id, $this->orgId);
        $canEditRecord = Roles::hasFullAccess($this->roleId) || $this->userId === $payment->createdBy;
        if (!$canEditRecord) {
            log_error("IDOR attempt: User {$this->userId} tried to update payment $id", 'WARNING', __FILE__, __LINE__);
            return new Response('Forbidden', 403);
        }

        $paymentData = $this->buildPaymentData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $this->paymentService->updatePayment($id, $paymentData, $itemsData, $this->orgId, $this->userId);

            PdfGeneratorHelper::ensure('payments_received', $id);

            if ($request->get('save_and_send') == 1) {
                return Response::redirect("send_email.php?current_module=payments_received&id=$id");
            }
            updateCustomerLogs((int)($paymentData['customer_id'] ?? 0), 'payment', 'edit', $id);
            flash_success('The Payment Received has been updated successfully.');
            return Response::redirect("payment_received_overview.php?payment_received_id=$id");
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            $_SESSION['__payments_received_old_input'] = $_POST;
            return Response::redirect("payments_received.php?id=$id&action=edit_payments_received");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'payments_received',
                'module_slug' => 'payments_received',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            $_SESSION['__payments_received_old_input'] = $_POST;
            return Response::redirect("payments_received.php?id=$id&action=edit_payments_received");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $paymentData = $this->buildPaymentData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $newPayment = $this->paymentService->createPayment($paymentData, $itemsData, $this->orgId, $this->userId);
            $id = $newPayment->id;

            PdfGeneratorHelper::ensure('payments_received', $id);

            if ($request->get('save_and_send') == 1) {
                return Response::redirect("send_email.php?current_module=payments_received&id=$id");
            }
            updateCustomerLogs((int)($paymentData['customer_id'] ?? 0), 'payment', 'add', $id);
            flash_success('The Payment Received has been saved successfully.');
            return Response::redirect("payment_received_overview.php?payment_received_id=$id");
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            $_SESSION['__payments_received_old_input'] = $_POST;
            return Response::redirect("payments_received.php");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'payments_received',
                'module_slug' => 'payments_received',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            $_SESSION['__payments_received_old_input'] = $_POST;
            return Response::redirect("payments_received.php");
        }
    }

    private function handleDelete(Request $request, int $id): Response
    {
        try {
            $this->paymentService->deletePayment($id, $this->orgId);
            flash_success('The Payment Received has been deleted successfully.');
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'payments_received',
                'module_slug' => 'payments_received',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
        }
        return Response::redirect('listing_payments_received.php');
    }

    private function handleClone(Request $request, int $id): Response
    {
        try {
            $cloned = $this->paymentService->clonePayment($id, $this->orgId, $this->userId);
            flash_success('The Payment Received has been cloned successfully.');
            return Response::redirect("payment_received_overview.php?payment_received_id={$cloned->id}");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'payments_received',
                'module_slug' => 'payments_received',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("payment_received_overview.php?payment_received_id=$id");
        }
    }

    private function handleVoid(Request $request, int $id): Response
    {
        try {
            $this->paymentService->voidPayment($id, $this->orgId, $this->userId);
            flash_success('The Payment Received has been voided successfully.');
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'payments_received',
                'module_slug' => 'payments_received',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
        }
        return Response::redirect("payment_received_overview.php?payment_received_id=$id");
    }

    private function buildPaymentData(Request $request): array
    {
        return [
            'customer_id' => $request->getString('customer_id'),
            'payment_date' => $request->getString('payment_date'),
            'payment_method' => $request->getString('payment_method'),
            'deposit_to' => $request->getString('deposit_to'),
            'total_amount_received' => $request->getString('total_amount_received'),
            'bank_charges' => $request->getString('bank_charges'),
            'reference_no' => $request->getString('reference_no'),
            'payment_status' => $request->getString('payment_status', 'draft'),
            'publish' => true,
            'is_active' => true,
        ];
    }

    private function buildItemsData(Request $request): array
    {
        $totalRows = (int)$request->getString('total_rows', '0');
        $items = [];

        for ($i = 0; $i < $totalRows; $i++) {
            $invoiceId = $request->getArrayItem('item_id', $i);
            $amountReceived = $request->getArrayItem('amount_received', $i);
            $amountReceivedOn = $request->getArrayItem('amount_received_on', $i);

            if (empty($invoiceId) || empty($amountReceived)) {
                continue;
            }

            $items[] = [
                'invoice_id' => (int)$invoiceId,
                'amount_received' => (float)$amountReceived,
                'amount_received_on' => $amountReceivedOn,
            ];
        }

        return $items;
    }

    private function showForm(Request $request, int $id): Response
    {
        $module = 'payments_received';
        $moduleCaption = $this->moduleCaption;
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;
        $session_role_id = $this->roleId;
        $error_message = $request->getString('error_message');
        if (empty($error_message)) {
            foreach (\App\Core\FlashMessage::all() as $fm) {
                if ($fm['type'] === 'danger') { $error_message = $fm['message']; break; }
            }
        }
        $action = $request->getString('action');

        // Default values
        $payment_no = '';
        $payment_status = 'draft';
        $customer_id = (string)$request->getString('customer_id', '0');

        // When coming from invoice "Record Payment" link (post_invoice_id only, no customer_id),
        // look up the invoice's customer so the form populates correctly.
        if ((int)$customer_id === 0) {
            $lookupInvoiceId = $request->getInt('post_invoice_id');
            if ($lookupInvoiceId > 0) {
                $invoiceRow = $this->db->fetchOne("SELECT customer_id FROM `" . DB::INVOICES . "` WHERE id = :id AND organization_id = :org_id", ['id' => $lookupInvoiceId, 'org_id' => $this->orgId]);
                if ($invoiceRow) {
                    $customer_id = (string)$invoiceRow['customer_id'];
                }
            }
        }

        $total_amount_received = '0.00';
        $bank_charges = '0.00';
        $payment_date = date('Y-m-d');
        $payment_method = '';
        $deposit_to = '';
        $reference_no = '';
        $is_active = 1;

        $item_id_arr = [];
        $amount_received_on_arr = [];
        $amount_received_arr = [];
        $total_rows = 0;

        if ($id > 0) {
            $created_by = 0;
            try {
                $sql = "SELECT created_by FROM `" . DB::PAYMENTS_RECEIVED . "` WHERE id = :id";
                $row = $this->db->fetchOne($sql, ['id' => $id]);
                $created_by = $row ? (int)$row['created_by'] : 0;
            } catch (\Throwable $e) {
                $created_by = 0;
            }

            $canEdit = Roles::hasFullAccess($session_role_id) || $this->canEdit() || $session_user_id === $created_by;

            if ($canEdit) {
                try {
                    $payment = $this->paymentService->getPayment($id, $this->orgId);
                    $customer_id = (string)$payment->customerId;
                    $payment_no = (string)$payment->paymentNo;
                    $payment_status = $payment->paymentStatus;
                    $total_amount_received = (string)$payment->totalAmountReceived;
                    $bank_charges = (string)$payment->bankCharges;
                    $payment_date = $payment->paymentDate;
                    $payment_method = $payment->paymentMethod !== null ? (string)$payment->paymentMethod : '';
                    $deposit_to = (string)$payment->depositTo;
                    $reference_no = (string)$payment->referenceNo;
                    $is_active = $payment->isActive ? 1 : 0;

                    $payment_date = ($payment_date === '1970-01-01') ? '' : DateHelper::toDisplayDate($payment_date);

                    $paymentItems = $this->paymentService->getPaymentItems($id, $this->orgId);
                    $total_rows = count($paymentItems);

                    foreach ($paymentItems as $item) {
                        $item_id_arr[] = $item->invoiceId;
                        $amount_received_on_arr[] = $item->amountReceivedOn;
                        $amount_received_arr[] = $item->amountReceived;
                    }
                } catch (\Throwable $e) {
                    log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                        'module' => 'payments_received',
                        'module_slug' => 'payments_received',
                        'stack_trace' => $e->getTraceAsString(),
                        'error_code' => (string)$e->getCode(),
                    ]));
                    $error_message = $e->getMessage();
                }
            }
        }

        if ($total_rows == 0) {
            $total_rows = 1;
        }

        // Restore old form input after failed save
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (isset($_SESSION['__payments_received_old_input'])) {
            $old = $_SESSION['__payments_received_old_input'];
            unset($_SESSION['__payments_received_old_input']);

            foreach (['customer_id', 'payment_date', 'payment_method', 'deposit_to',
                       'total_amount_received', 'bank_charges', 'reference_no',
                       'payment_status'] as $key) {
                if (isset($old[$key])) {
                    $$key = (string)$old[$key];
                }
            }

            if (isset($old['publish'])) {
                $is_active = $old['publish'] ? 1 : 0;
            }

            if (isset($old['item_id']) && is_array($old['item_id'])) {
                $item_id_arr = $old['item_id'];
                $amount_received_on_arr = $old['amount_received_on'] ?? [];
                $amount_received_arr = $old['amount_received'] ?? [];
                $total_rows = max(1, count($old['item_id']));
            }
        }

        // Fetch dropdown data
        try {
            $customersList = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::CUSTOMERS . "` WHERE is_active=1 AND approved=1 AND organization_id = :org_id ORDER BY id DESC", ['org_id' => $this->orgId]);
        } catch (\Throwable $e) {
            $customersList = [];
            if (function_exists('log_error')) {
                log_error('payments_received form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'payments_received', 'module_slug' => 'payments_received', 'error_code' => (string)$e->getCode()]) : ['module' => 'payments_received', 'module_slug' => 'payments_received', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $paymentMethodsList = $this->db->fetchAll("SELECT id, payment_method FROM `" . DB::PAYMENT_METHODS . "` WHERE is_active=1 ORDER BY id ASC");
        } catch (\Throwable $e) {
            $paymentMethodsList = [];
            if (function_exists('log_error')) {
                log_error('payments_received form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'payments_received', 'module_slug' => 'payments_received', 'error_code' => (string)$e->getCode()]) : ['module' => 'payments_received', 'module_slug' => 'payments_received', 'error_code' => (string)$e->getCode()]);
            }
        }
        try {
            $depositAccountsList = $this->db->fetchAll("SELECT id, account_name FROM `" . DB::ACCOUNTS . "` WHERE is_active=1 AND account_type IN ('Asset','Assets') ORDER BY account_name ASC");
        } catch (\Throwable $e) {
            $depositAccountsList = [];
            if (function_exists('log_error')) {
                log_error('payments_received form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'payments_received', 'module_slug' => 'payments_received', 'error_code' => (string)$e->getCode()]) : ['module' => 'payments_received', 'module_slug' => 'payments_received', 'error_code' => (string)$e->getCode()]);
            }
        }

        // Unpaid invoices for the selected customer (with balance due calculation)
        $invoicesList = [];
        if ((int)$customer_id > 0) {
            try {
                $postInvoiceId = $request->getInt('post_invoice_id');
                $whereExtra = $postInvoiceId > 0 ? " AND i.id = :post_invoice_id" : "";
                $params = ['customer_id' => (int)$customer_id, 'org_id' => $this->orgId];
                if ($postInvoiceId > 0) {
                    $params['post_invoice_id'] = $postInvoiceId;
                }
                $sql = "SELECT i.id, i.invoice_no, i.invoice_date, i.grand_total,
                        (i.grand_total - IFNULL((SELECT SUM(pri.amount_received) FROM `" . DB::PAYMENT_RECEIVED_ITEMS . "` pri
                            INNER JOIN `" . DB::PAYMENTS_RECEIVED . "` pr ON pr.id = pri.payment_id
                            WHERE pri.invoice_id = i.id AND pr.payment_status <> 'void'),0)) AS balance_due
                        FROM `" . DB::INVOICES . "` i
                        WHERE i.customer_id = :customer_id AND i.organization_id = :org_id
                        AND i.invoice_status NOT IN ('paid', 'writeoff', 'void')" . $whereExtra . "
                        ORDER BY i.id DESC";
                $invoicesList = $this->db->fetchAll($sql, $params);
            } catch (\Throwable $e) {
                $invoicesList = [];
                if (function_exists('log_error')) {
                    log_error('payments_received form dropdown load failed: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, function_exists('backend_runtime_log_context') ? backend_runtime_log_context(['module' => 'payments_received', 'module_slug' => 'payments_received', 'error_code' => (string)$e->getCode()]) : ['module' => 'payments_received', 'module_slug' => 'payments_received', 'error_code' => (string)$e->getCode()]);
                }
            }
        }

        return Response::html($this->view->render('payments_received/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'payment_no' => $payment_no,
            'payment_status' => $payment_status,
            'customer_id' => $customer_id,
            'total_amount_received' => $total_amount_received,
            'bank_charges' => $bank_charges,
            'payment_date' => $payment_date,
            'payment_method' => $payment_method,
            'deposit_to' => $deposit_to,
            'reference_no' => $reference_no,
            'is_active' => $is_active,
            'total_rows' => $total_rows,
            'item_id_arr' => $item_id_arr,
            'amount_received_on_arr' => $amount_received_on_arr,
            'amount_received_arr' => $amount_received_arr,
            'customersList' => $customersList,
            'paymentMethodsList' => $paymentMethodsList,
            'depositAccountsList' => $depositAccountsList,
            'invoicesList' => $invoicesList,
            'post_invoice_id' => $request->getInt('post_invoice_id'),
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
