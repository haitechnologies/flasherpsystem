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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if (($action == "delete_$module" && !empty($id))) {
    if (Session::roleId() == '1') {
        $mysqli->query("DELETE FROM `" . DB::SALE_ORDER_ITEMS . "` WHERE sale_order_id=$id");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id ");
    } else {
        $mysqli->query("DELETE FROM `" . DB::SALE_ORDER_ITEMS . "` WHERE sale_order_id=$id");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id AND created_by='" . Session::userId() . "'");
    }

    if ($mysqli->affected_rows > 0) {
        $success_message = "$module_caption Deleted Successfully.";
        flash_success($success_message);
        header("Location:listing_$module.php?page=$page");
    } else {
        $error_message = "Sorry! $module Could Not Be Deleted. Only Super Administrator can delete this record.";
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
                    var link = '<a href=\"sale_order_overview.php?sale_order_id=' + row[6] + '\" class=\"text-primary fw-semibold\">' + row[1] + '</a>';
                    $(cell).html(link);
                }
            });
        });
    ",
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
