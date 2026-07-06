<?php

use App\Core\DB;

include('admin_elements/admin_header.php');

$module = 'banks';
$module_caption = 'Bank';
$tbl_name = DB::BANKS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$handler_config = ['hard_delete' => true, 'ownership_check' => true, 'redirect_on_success' => true];
include('admin_elements/listing_handler.php');

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th width="40">SR.</th>
        <th width="50" class="col-center">PRIMARY</th>
        <th width="80" class="col-center">STATUS</th>
        <th>ACCOUNT NAME</th>
        <th width="150">CURRENCY</th>
        <th>ACCOUNT CODE</th>
        <th>BANK NAME</th>
        <th>BRANCH</th>
        <th>IBAN</th>
        <th>ROUTING NUMBER</th>
        <th width="90">CREATED AT</th>
        <th width="90" class="col-center">ACTIONS</th>
    ',
    'columns' => [
        ['data' => 0, 'orderable' => false, 'searchable' => false],
        ['data' => 1, 'orderable' => false, 'searchable' => false, 'className' => 'col-center'],
        ['data' => 2, 'orderable' => false, 'searchable' => false, 'className' => 'col-center'],
        ['data' => 3],
        ['data' => 4],
        ['data' => 5],
        ['data' => 6],
        ['data' => 7],
        ['data' => 8],
        ['data' => 9],
        ['data' => 10],
        ['data' => 11, 'orderable' => false, 'searchable' => false, 'className' => 'col-center'],
    ],
    'order' => [[3, 'asc']],
    'page_length' => 25,
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
