<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Container;
use App\Core\Database;
use App\Core\Session;
use App\Service\DepartmentService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

include('admin_elements/admin_header.php');

$module = 'departments';
$module_caption = 'Department';
$tbl_name = DB::DEPARTMENTS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$container   = Container::getInstance();
$db          = $container->get(Database::class);
$deptService = $container->get(DepartmentService::class);

// DELETE
if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    try {
        if (!is_SuperAdmin()) {
            $dept = $deptService->getById((int)$id);
            if ($dept->createdBy !== (int)Session::userId()) {
                $error_message = "You do not have permission to delete this department.";
            }
        }

        if (empty($error_message)) {
            $deptService->delete((int)$id);
            $success_message = "Item deleted successfully.";
            flash_success($success_message);
            header("Location:listing_$module.php");
            exit;
        }
    } catch (ValidationException $e) {
        $error_message = current($e->getErrors());
    } catch (NotFoundException $e) {
        $error_message = $e->getMessage();
    } catch (\Throwable $e) {
        error_log("Department delete error: " . $e->getMessage());
        $error_message = "An error occurred while deleting the department.";
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th width="40">SR.</th>
        <th>DEPARTMENT</th>
        <th>EMAIL</th>
        <th>EMPLOYEES</th>
        <th width="90">CREATED AT</th>
        <th width="90" class="col-center">ACTIONS</th>
    ',
    'columns' => [
        ['data' => 0, 'orderable' => false, 'searchable' => false],
        ['data' => 1],
        ['data' => 2, 'orderable' => false, 'searchable' => false],
        ['data' => 3, 'orderable' => false, 'searchable' => false],
        ['data' => 4],
        ['data' => 5, 'orderable' => false, 'searchable' => false, 'className' => 'col-center'],
    ],
    'order' => [[0, 'asc']],
    'page_length' => 25,
];



include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
