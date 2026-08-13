<?php

declare(strict_types=1);

use App\Core\DB;
use App\Service\UnitService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

include('admin_elements/admin_header.php');

$module = 'units';
$module_caption = 'Unit';
$tbl_name = DB::UNITS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$unitService = $container->get(UnitService::class);

if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    try {
        if (!is_SuperAdmin()) {
            $model = $unitService->getById((int)$id);
            if ($model && $model->createdBy !== (int)Session::userId()) {
                $error_message = "Action denied. You are not authorized to delete this record.";
            }
        }
        if (empty($error_message)) {
            $unitService->delete((int)$id);
            $success_message = "Item deleted successfully.";
            flash_success($success_message);
            header("Location:listing_$module.php");
            exit;
        }
    } catch (ValidationException $e) {
        $error_message = current($e->getErrors());
        log_error($e->getMessage(), 'WARNING', $e->getFile(), $e->getLine(), ['module' => 'units', 'action' => 'delete']);
    } catch (NotFoundException $e) {
        $error_message = $e->getMessage();
        log_error($e->getMessage(), 'WARNING', $e->getFile(), $e->getLine(), ['module' => 'units', 'action' => 'delete']);
    } catch (\Throwable $e) {
        $error_message = "An error occurred while deleting the record.";
        log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'units', 'action' => 'delete']);
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th width="40">SR.</th>
        <th>UNIT</th>
        <th width="90">CREATED AT</th>
        <th width="80" class="col-center">STATUS</th>
        <th width="90" class="col-center">ACTIONS</th>
    ',
    'columns' => [
        ['data' => 0, 'orderable' => false, 'searchable' => false],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3, 'className' => 'col-center'],
        ['data' => 4, 'orderable' => false, 'searchable' => false, 'className' => 'col-center'],
    ],
    'order' => [[0, 'asc']],
    'page_length' => 25,
    'search_placeholder' => 'Search units...',
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
