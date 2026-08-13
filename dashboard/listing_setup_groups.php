<?php

use App\Core\DB;
use App\Service\SetupGroupService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

include('admin_elements/admin_header.php');

$module = 'setup_groups';
$module_caption = 'Group';
$tbl_name = DB::TAXONOMIES;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$groupService = $container->get(SetupGroupService::class);

// DELETE
if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    try {
        if (!is_SuperAdmin()) {
            $model = $groupService->getById((int)$id);
            if ($model && $model->createdBy !== (int)Session::userId()) {
                $error_message = "Action denied. You are not authorized to delete this record.";
            }
        }
        if (empty($error_message)) {
            $groupService->delete((int)$id);
            $success_message = "Item deleted successfully.";
            flash_success($success_message);
            header("Location:listing_$module.php");
            exit;
        }
    } catch (ValidationException $e) {
        $error_message = current($e->getErrors());
        log_error($e->getMessage(), 'WARNING', $e->getFile(), $e->getLine(), ['module' => 'setup_groups', 'action' => 'delete']);
    } catch (NotFoundException $e) {
        $error_message = $e->getMessage();
        log_error($e->getMessage(), 'WARNING', $e->getFile(), $e->getLine(), ['module' => 'setup_groups', 'action' => 'delete']);
    } catch (\Throwable $e) {
        $error_message = "An error occurred while deleting the record.";
        log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'setup_groups', 'action' => 'delete']);
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th width="40">SR.</th>
        <th>GROUP NAME</th>
        <th>DESCRIPTION</th>
        <th width="90">CREATED AT</th>
        <th width="50">STATUS</th>
        <th width="90">ACTIONS</th>
    ',
    'columns' => [
        ['data' => 0, 'orderable' => false],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4, 'className' => 'text-center'],
        ['data' => 5, 'className' => 'text-center'],
    ],
    'order' => [[1, 'asc']],
    'page_length' => 25,
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
