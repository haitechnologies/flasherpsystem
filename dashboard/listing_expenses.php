<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Session;
use App\Core\Container;
use App\Service\ExpenseService;

include('admin_elements/admin_header.php');

$module = 'expenses';
$module_caption = 'Expense';
$tbl_name = DB::EXPENSES;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if (!empty($action) && $action == "delete_$module") {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
        log_error('Invalid CSRF token on expense delete', 'SECURITY', __FILE__, __LINE__, backend_runtime_log_context([
            'module' => 'expenses',
            'module_slug' => 'expenses',
        ]));
        $action = '';
    }
}

if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    $expenseId = (int)$id;
    $orgId = (int)$activeOrganizationId;
    try {
        $expenseService = Container::getInstance()->get(ExpenseService::class);
        $expenseService->deleteExpense($expenseId, $orgId);
        $success_message = "Item deleted successfully.";
        flash_success($success_message);
        header("Location:listing_$module.php");
        exit;
    } catch (\Throwable $e) {
        log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), backend_runtime_log_context([
            'module' => 'expenses',
            'module_slug' => 'expenses',
            'expense_id' => $expenseId,
        ]));
        $error_message = "Action denied. You are not authorized to delete this record.";
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th width="100">DATE</th>
        <th>EXPENSE ACCOUNT</th>
        <th>REFERENCE#</th>
        <th>VENDOR NAME</th>
        <th>PAID THROUGH</th>
        <th>CUSTOMER NAME</th>
        <th width="80" class="col-center">STATUS</th>
        <th width="100" class="text-end">AMOUNT</th>
    ',
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4],
        ['data' => 5],
        ['data' => 6, 'className' => 'col-center'],
        ['data' => 7, 'className' => 'text-end'],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
    'search_placeholder' => 'Search expenses...',
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
