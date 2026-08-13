<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Container;
use App\Core\Database;
use App\Core\Session;
use App\Service\DesignationService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

include('admin_elements/admin_header.php');

$module = 'designations';
$module_caption = 'Designation';
$tbl_name = DB::DESIGNATIONS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$container = Container::getInstance();
$db = $container->get(Database::class);
$designationService = $container->get(DesignationService::class);

// DELETE
if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    try {
        if (!is_SuperAdmin()) {
            $desg = $designationService->getById((int)$id);
            if ($desg->createdBy !== (int)Session::userId()) {
                $error_message = "You do not have permission to delete this designation.";
            }
        }

        if (empty($error_message)) {
            $designationService->delete((int)$id);
            $success_message = "$module_caption Deleted Successfully.";
            flash_success($success_message);
            header("Location:listing_$module.php");
            exit;
        }
    } catch (ValidationException $e) {
        $error_message = current($e->getErrors());
        log_error($e->getMessage(), 'WARNING', $e->getFile(), $e->getLine(), ['module' => 'designations', 'action' => 'delete']);
    } catch (NotFoundException $e) {
        $error_message = $e->getMessage();
        log_error($e->getMessage(), 'WARNING', $e->getFile(), $e->getLine(), ['module' => 'designations', 'action' => 'delete']);
    } catch (\Throwable $e) {
        log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'designations', 'action' => 'delete']);
        $error_message = "Sorry! $module Could Not Be Deleted.";
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th width="40">SR.</th>
        <th>DESIGNATION</th>
        <th>EMPLOYEES</th>
        <th width="90">CREATED AT</th>
        <th width="90" class="col-center">ACTIONS</th>
    ',
    'columns' => [
        ['data' => 0, 'orderable' => false, 'searchable' => false],
        ['data' => 1],
        ['data' => 2, 'orderable' => false, 'searchable' => false],
        ['data' => 3],
        ['data' => 4, 'orderable' => false, 'searchable' => false, 'className' => 'col-center'],
    ],
    'order' => [[0, 'asc']],
    'page_length' => 25,
];



include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
