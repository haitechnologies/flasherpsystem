<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Session;

include('admin_elements/admin_header.php');

$module = 'sale_orders';
$module_caption = 'Sale Order';
$tbl_name = DB::SALE_ORDERS;
$error_message = '';
$success_message = '';
$page = (int)($_GET['page'] ?? 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in listing_sale_orders.php', 'WARNING', __FILE__, __LINE__);
        $_POST['action'] = '';
    }
}

$action = $_POST['action'] ?? $_REQUEST['action'] ?? '';
$id = $_POST['id'] ?? $_REQUEST['id'] ?? 0;

if (($action == "delete_$module" && !empty($id))) {
    $idResult = \App\Security\InputValidator::integer($id, 1);
    if (!$idResult['valid']) {
        $error_message = "Invalid sale order ID: " . $idResult['error'];
    } else {
        $validId = $idResult['value'];
        try {
            $saleOrderService = \App\Core\Container::getInstance()->get(\App\Service\SaleOrderService::class);

            if (!\App\Security\Roles::hasFullAccess(\App\Core\Session::roleId())) {
                $so = $saleOrderService->getSaleOrder($validId, $activeOrganizationId);
                if ($so->createdBy !== \App\Core\Session::userId()) {
                    throw new \Exception("You do not have permission to delete this sale order");
                }
            }

            if ($saleOrderService->deleteSaleOrder($validId, $activeOrganizationId)) {
                $success_message = "$module_caption Deleted Successfully.";
                flash_success($success_message);
                header("Location:listing_$module.php?page=$page");
                exit;
            } else {
                $error_message = "Sorry! $module_caption Could Not Be Deleted.";
            }
        } catch (\Throwable $e) {
            $error_message = $e->getMessage();
            log_error("Delete failed for sale order $validId: " . $e->getMessage(), 'ERROR', __FILE__, __LINE__);
        }
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th width="100">DATE</th>
        <th width="150">SALE ORDER #</th>
        <th>REFERENCE #</th>
        <th>CUSTOMER NAME</th>
        <th width="100" class="col-center">STATUS</th>
        <th width="100" class="text-end">AMOUNT</th>
    ',
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4, 'className' => 'col-center'],
        ['data' => 5, 'className' => 'text-end'],
        ['data' => 6, 'visible' => false],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
    'search_placeholder' => 'Search sale orders...',
    'extra_js' => "
        var table = $('#grid-{$module}').DataTable();
        table.on('draw', function() {
            table.column(1, { page: 'current' }).nodes().each(function(cell, i) {
                var row = table.row(cell).data();
                if (row && row[6]) {
                    var link = '<a href=\"sale_order_overview.php?sale_order_id=' + row[6] + '\" class=\"text-primary\">' + row[1] + '</a>';
                    $(cell).html(link);
                }
            });
        });
    ",
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
