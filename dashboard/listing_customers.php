<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Container;
use App\Service\CustomerService;
use App\Exception\NotFoundException;

include('admin_elements/admin_header.php');

$module = 'customers';
$module_caption = 'Customer';
$tbl_name = DB::CUSTOMERS;
$module_id = getModuleIdBySlug($module, $mysqli);
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($action)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in listing_customers.php', 'WARNING', __FILE__, __LINE__);
        $action = '';
    }
}

$container = Container::getInstance();
$customerService = $container->get(CustomerService::class);

if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    try {
        $customerObj = $customerService->getCustomer((int)$id, $activeOrganizationId);

        $canDelete = has_full_access() || (int)$customerObj->createdBy === (int)Session::userId() || (int)$customerObj->customerOwner === (int)Session::userId();

        if (!$canDelete) {
            $error_message = "You do not have permission to delete this customer";
            log_error("IDOR attempt: User Session::userId() tried to delete customer $id", 'WARNING', __FILE__, __LINE__);
        } else {
            $customerService->deleteCustomer((int)$id, $activeOrganizationId);
            $success_message = "Customer deleted successfully.";
            flash_success($success_message);
            header("Location:listing_$module.php");
            exit;
        }
    } catch (NotFoundException $e) {
        $error_message = $e->getMessage();
    } catch (\Throwable $e) {
        $error_message = "An error occurred while deleting the customer.";
    }
}

$customerStatusHtml = '';
$rsStatus = $mysqli->query("SELECT * FROM `" . DB::TAXONOMIES . "` WHERE is_active=1 AND type='customer_status' ORDER BY value LIMIT 50");
while ($rows = $rsStatus->fetch_array()) {
    $status = $rows['id'];
    $rs = $mysqli->query("SELECT id FROM `" . DB::CUSTOMERS . "` WHERE customer_status=$status");
    $customerStatusHtml .= '<span class="badge bg-light text-dark"><a href="listing_customers.php?customer_status=' . $status . '" class="text-black fw-normal">' . htmlspecialchars($rows['value']) . ' (' . $rs->num_rows . ')</a></span> ';
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th>NAME</th>
        <th>EMAIL</th>
        <th>WORK PHONE</th>
        <th class="col-center">RECEIVABLES (BCY)</th>
        <th class="col-center">STATUS</th>
        <th class="col-center">APPROVAL</th>
        <th>ACTIONS</th>
    ',
    'columns' => [
        ['data' => 0, 'name' => 'display_name', 'title' => 'NAME'],
        ['data' => 1, 'name' => 'email', 'title' => 'EMAIL'],
        ['data' => 2, 'name' => 'phone', 'title' => 'WORK PHONE'],
        ['data' => 3, 'name' => 'receivables', 'title' => 'RECEIVABLES (BCY)'],
        ['data' => 4, 'name' => 'is_active', 'title' => 'STATUS'],
        ['data' => 5, 'name' => 'approved', 'title' => 'APPROVAL'],
        ['data' => 6, 'title' => 'ACTIONS', 'orderable' => false, 'searchable' => false],
    ],
    'order' => [[0, 'asc']],
    'page_length' => 10,
    'table_classes' => 'custom_datatables datatable-professional display responsive no-wrap table-hover',
    'before_table' => '<div class="row mb-2 mt-2"><div class="col-lg-12">' . $customerStatusHtml . '</div></div>',
    'extra_js' => "
        var customerStatus = new URLSearchParams(window.location.search).get('customer_status') || '';
    ",
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
