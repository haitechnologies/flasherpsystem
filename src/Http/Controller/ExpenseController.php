<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\ExpenseService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Helper\DateHelper;

class ExpenseController extends BaseController
{
    private ExpenseService $expenseService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        ExpenseService $expenseService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->expenseService = $expenseService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('expenses', 'Expense');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_expenses' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_expenses' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($request, $id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $expenseData = $this->buildExpenseData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $this->expenseService->updateExpense($id, $expenseData, $itemsData, $this->orgId, $this->userId);
            flash_success('The Expense has been updated successfully.');
            return Response::redirect('listing_expenses.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            return $this->renderFormWithData($expenseData, $itemsData, $error, $id);
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'expenses',
                'module_slug' => 'expenses',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            return $this->renderFormWithData($expenseData, $itemsData, $e->getMessage(), $id);
        }
    }

    private function handleCreate(Request $request): Response
    {
        $expenseData = $this->buildExpenseData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $this->expenseService->createExpense($expenseData, $itemsData, $this->orgId, $this->userId);
            flash_success('The Expense has been saved successfully.');
            return Response::redirect('listing_expenses.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            return $this->renderFormWithData($expenseData, $itemsData, $error, 0);
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'expenses',
                'module_slug' => 'expenses',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            return $this->renderFormWithData($expenseData, $itemsData, $e->getMessage(), 0);
        }
    }

    private function buildExpenseData(Request $request): array
    {
        return [
            'expense_date' => $request->getString('expense_date'),
            'paid_through' => $request->getString('paid_through'),
            'vendor_id' => $request->getString('vendor_id'),
            'reference_no' => $request->getString('reference_no'),
            'customer_id' => $request->getString('customer_id'),
            'billable' => $request->get('billable') ? 1 : 0,
            'grand_total' => $request->getString('grand_total', '0.00'),
        ];
    }

    private function buildItemsData(Request $request): array
    {
        $totalRows = (int)$request->getString('total_rows', '1');
        $items = [];

        for ($i = 0; $i < $totalRows; $i++) {
            $itemId = $request->getArrayItem('item_id', $i);
            $expenseAccount = $request->getArrayItem('expense_account', $i);
            $total = $request->getArrayItem('total', $i, '0');

            if (empty($expenseAccount) || (int)$expenseAccount <= 0) {
                continue;
            }

            $items[] = [
                'item_id' => !empty($itemId) ? (int)$itemId : null,
                'expense_account' => (int)$expenseAccount,
                'description' => $request->getArrayItem('description', $i),
                'total' => (float)$total,
            ];
        }

        return $items;
    }

    private function showForm(Request $request, int $id): Response
    {
        $module = 'expenses';
        $moduleCaption = $this->moduleCaption;
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;
        $session_role_id = $this->roleId;
        $error_message = $request->getString('error_message');
        $action = $request->getString('action');

        $expense_date = date('d-m-Y');
        $paid_through = '';
        $vendor_id = '0';
        $reference_no = '';
        $customer_id = '0';
        $billable = 0;
        $grand_total = '0.00';

        $item_id_arr = [];
        $expense_account_arr = [];
        $description_arr = [];
        $total_arr = [];
        $total_rows = 1;

        if ($id > 0) {
            $created_by = 0;
            try {
                $sql = "SELECT created_by FROM `" . DB::EXPENSES . "` WHERE id = :id";
                $row = $this->db->fetchOne($sql, ['id' => $id]);
                $created_by = $row ? (int)$row['created_by'] : 0;
            } catch (\Throwable $e) {
                $created_by = 0;
            }

            $canEdit = Roles::hasFullAccess($session_role_id) || $this->canEdit() || $session_user_id === $created_by;

            if ($canEdit) {
                try {
                    $expense = $this->expenseService->getExpense($id, $this->orgId);
                    $expense_date = DateHelper::toDisplayDate($expense->expenseDate) ?: $expense->expenseDate;
                    $paid_through = (string)$expense->paidThrough;
                    $vendor_id = (string)$expense->vendorId;
                    $reference_no = (string)$expense->referenceNo;
                    $customer_id = (string)$expense->customerId;
                    $billable = $expense->billable ? 1 : 0;
                    $grand_total = (string)$expense->grandTotal;

                    $items = $this->expenseService->getExpenseItems($id, $this->orgId);
                    $total_rows = count($items);

                    foreach ($items as $item) {
                        $item_id_arr[] = $item->id;
                        $expense_account_arr[] = $item->expenseAccount;
                        $description_arr[] = $item->description;
                        $total_arr[] = $item->total;
                    }
                } catch (\Throwable $e) {
                    log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                        'module' => 'expenses',
                        'module_slug' => 'expenses',
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

        $vendorsList = [];
        $customersList = [];
        try {
            $vendorsList = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::VENDORS . "` ORDER BY id DESC");
        } catch (\Throwable $e) {
                log_error('Failed to load form dropdown: ' . $e->getMessage(), 'WARNING', $e->getFile(), $e->getLine(), ['module' => 'expenses', 'action' => 'load_form_dropdown']);
}
        try {
            $customersList = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::CUSTOMERS . "` WHERE is_active=1 AND approved=1 ORDER BY id DESC");
        } catch (\Throwable $e) {
                log_error('Failed to load form dropdown: ' . $e->getMessage(), 'WARNING', $e->getFile(), $e->getLine(), ['module' => 'expenses', 'action' => 'load_form_dropdown']);
}

        return Response::html($this->view->render('expenses/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'expense_date' => $expense_date,
            'paid_through' => $paid_through,
            'vendor_id' => $vendor_id,
            'reference_no' => $reference_no,
            'customer_id' => $customer_id,
            'billable' => $billable,
            'grand_total' => $grand_total,
            'total_rows' => $total_rows,
            'item_id_arr' => $item_id_arr,
            'expense_account_arr' => $expense_account_arr,
            'description_arr' => $description_arr,
            'total_arr' => $total_arr,
            'vendorsList' => $vendorsList,
            'customersList' => $customersList,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }

    private function renderFormWithData(array $expenseData, array $itemsData, string $errorMessage, int $id): Response
    {
        $error_message = $errorMessage;
        $module = 'expenses';
        $moduleCaption = $this->moduleCaption;
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;
        $session_role_id = $this->roleId;

        $expense_date = DateHelper::toDisplayDate($expenseData['expense_date'] ?? '') ?: ($expenseData['expense_date'] ?? date('d-m-Y'));
        $paid_through = $expenseData['paid_through'] ?? '';
        $vendor_id = $expenseData['vendor_id'] ?? '0';
        $reference_no = $expenseData['reference_no'] ?? '';
        $customer_id = $expenseData['customer_id'] ?? '0';
        $billable = $expenseData['billable'] ?? 0;
        $grand_total = $expenseData['grand_total'] ?? '0.00';

        $item_id_arr = [];
        $expense_account_arr = [];
        $description_arr = [];
        $total_arr = [];

        foreach ($itemsData as $item) {
            $item_id_arr[] = $item['item_id'] ?? null;
            $expense_account_arr[] = $item['expense_account'] ?? '';
            $description_arr[] = $item['description'] ?? '';
            $total_arr[] = $item['total'] ?? '0';
        }

        $total_rows = count($itemsData) > 0 ? count($itemsData) : 1;

        $vendorsList = [];
        $customersList = [];
        try {
            $vendorsList = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::VENDORS . "` ORDER BY id DESC");
        } catch (\Throwable $e) {
                log_error('Failed to load form dropdown: ' . $e->getMessage(), 'WARNING', $e->getFile(), $e->getLine(), ['module' => 'expenses', 'action' => 'load_form_dropdown']);
}
        try {
            $customersList = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::CUSTOMERS . "` WHERE is_active=1 AND approved=1 ORDER BY id DESC");
        } catch (\Throwable $e) {
                log_error('Failed to load form dropdown: ' . $e->getMessage(), 'WARNING', $e->getFile(), $e->getLine(), ['module' => 'expenses', 'action' => 'load_form_dropdown']);
}

        return Response::html($this->view->render('expenses/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'expense_date' => $expense_date,
            'paid_through' => $paid_through,
            'vendor_id' => $vendor_id,
            'reference_no' => $reference_no,
            'customer_id' => $customer_id,
            'billable' => $billable,
            'grand_total' => $grand_total,
            'total_rows' => $total_rows,
            'item_id_arr' => $item_id_arr,
            'expense_account_arr' => $expense_account_arr,
            'description_arr' => $description_arr,
            'total_arr' => $total_arr,
            'vendorsList' => $vendorsList,
            'customersList' => $customersList,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
