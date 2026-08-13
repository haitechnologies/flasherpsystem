<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Container;
use App\Service\SalaryStructureService;
use App\Exception\NotFoundException;

$module = 'salary_structures';
$module_caption = 'Salary Structures';
$error_message = '';
$success_message = '';

include('admin_elements/admin_header.php');

$tbl_name = DB::SALARY_STRUCTURES;

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

$container = Container::getInstance();
/** @var SalaryStructureService $service */
$service = $container->get(SalaryStructureService::class);

if ($action == "delete_$module" && !empty($id) && (has_full_access() || (isset($module_id) && granted('delete', $module_id)))) {
    try {
        $service->delete((int)$id, $activeOrganizationId);
        $success_message = "Salary component deleted successfully.";
        flash_success($success_message);
        header("Location:listing_$module.php");
        exit;
    } catch (\Throwable $e) {
        $error_message = "Salary component could not be deleted.";
        log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'salary_structures', 'action' => 'delete']);
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th width="50">#</th>
        <th>Employee</th>
        <th>Component</th>
        <th>Amount</th>
        <th>Effective From</th>
        <th>Effective To</th>
        <th width="100" class="col-center">Action</th>
    ',
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4],
        ['data' => 5],
        ['data' => 6, 'orderable' => false, 'searchable' => false, 'className' => 'col-center text-center'],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
    'messages' => [
        'success' => $success_message ?? '',
        'error' => $error_message ?? ''
    ]
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
