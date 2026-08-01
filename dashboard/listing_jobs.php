<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Session;

include('admin_elements/admin_header.php');

$module = 'jobs';
$module_caption = 'Job';
$tbl_name = DB::JOBS;
$error_message = '';
$success_message = '';
$page = $_GET['page'] ?? '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if (($action == "delete_$module" && !empty($id))) {
    if (Session::roleId() == '1') {
        $mysqli->query("DELETE FROM `" . DB::QUOTATION_ITEMS . "` WHERE quotation_id=$id");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id ");
    } else {
        $mysqli->query("DELETE FROM `" . DB::QUOTATION_ITEMS . "` WHERE quotation_id=$id");
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
        <th>JOB #</th>
        <th>REFERENCE #</th>
        <th>CUSTOMER NAME</th>
        <th width="100" class="col-center">STATUS</th>
        <th width="100" class="text-end">AMOUNT</th>
        <th>PROJECT #</th>
        <th width="130" class="col-center">ACTIONS</th>
    ',
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4, 'className' => 'col-center'],
        ['data' => 5, 'className' => 'text-end'],
        ['data' => 6],
        ['data' => 7, 'orderable' => false, 'searchable' => false, 'className' => 'col-center'],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
    'button_text' => 'Create New Job',
    'search_placeholder' => 'Search jobs...',
    'extra_js' => <<<'JS'
        $(tableSelector + ' tbody').on('click', 'tr', function(e) {
            if ($(e.target).closest('a, button, .dropdown, .dropdown-menu').length > 0) return;
            var parts = $(this).find('td:last-child a[href*="jobs.php?"]').first().attr('href').split('id=');
            if (parts.length > 1) {
                window.location.href = 'view_job.php?job_id=' + parts[1];
            }
        });
    JS,
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
