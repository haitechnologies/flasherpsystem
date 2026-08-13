<?php

use App\Core\DB;
use App\Service\GratuitySettlementService;
use App\Core\Container;
use App\Exception\ValidationException;

include('admin_elements/admin_header.php');

$module = 'gratuity_settlements';
$module_caption = 'Gratuity Settlements';
$tbl_name = DB::GRATUITY_SETTLEMENTS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

$container = Container::getInstance();
/** @var GratuitySettlementService $gratuityService */
$gratuityService = $container->get(GratuitySettlementService::class);

if ($action == "delete_$module" && !empty($id) && (is_SystemAdmin() || is_SuperAdmin() || (isset($module_id) && granted('delete', $module_id)))) {
    try {
        $gratuityService->delete((int)$id);
        flash_success("Gratuity settlement deleted successfully.");
        header("Location:listing_$module.php");
        exit;
    } catch (ValidationException $e) {
        $error_message = current($e->getErrors());
    } catch (\Throwable $e) {
        $error_message = "Gratuity settlement could not be deleted.";
        log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'gratuity_settlements', 'action' => 'delete']);
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'hide_add_button' => false,
    'thead' => '
        <th width="50">SR.</th>
        <th>Employee</th>
        <th>Basic Salary</th>
        <th>Tenure</th>
        <th>Gratuity Amount</th>
        <th>Status</th>
        <th>Settlement Date</th>
        <th width="100" class="col-center">Action</th>
    ',
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2, 'className' => 'text-end'],
        ['data' => 3, 'className' => 'text-center'],
        ['data' => 4, 'className' => 'text-end'],
        ['data' => 5, 'className' => 'text-center'],
        ['data' => 6, 'className' => 'text-center'],
        ['data' => 7, 'orderable' => false, 'searchable' => false, 'className' => 'col-center text-center'],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
    'messages' => [
        'success' => $success_message ?? '',
        'error' => $error_message ?? ''
    ],
    'before_table' => '
        <div class="mb-3">
            <a href="gratuity_settlements.php" class="btn btn-secondary btn-sm">
                <i class="ph-calculator me-1"></i>Bulk Calculate
            </a>
        </div>
    ',
];



include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
