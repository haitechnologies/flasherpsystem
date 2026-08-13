<?php
declare(strict_types=1);

use App\Core\DB;
use App\Core\Container;
use App\Service\LeaveRequestService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

include('admin_elements/admin_header.php');

$module = 'leave_requests';
$module_caption = 'Leave Requests';
$tbl_name = DB::LEAVE_REQUESTS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

$container = Container::getInstance();
/** @var LeaveRequestService $leaveRequestService */
$leaveRequestService = $container->get(LeaveRequestService::class);

if ($action == "delete_$module" && !empty($id) && (is_SystemAdmin() || is_SuperAdmin() || (isset($module_id) && granted('delete', $module_id)))) {
    try {
        $leaveRequestService->delete((int)$id, $activeOrganizationId);
        $success_message = "Item deleted successfully.";
        flash_success($success_message);
        header("Location:listing_$module.php");
        exit;
    } catch (ValidationException $e) {
        $error_message = current($e->getErrors());
    } catch (NotFoundException $e) {
        $error_message = $e->getMessage();
    } catch (\Throwable $e) {
        $error_message = "Leave request could not be deleted.";
        log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'leave_requests', 'action' => 'delete']);
    }
}
$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th width="50">SR.</th>
        <th>Employee</th>
        <th>Leave Type</th>
        <th>Start</th>
        <th>End</th>
        <th>Days</th>
        <th>Paid/Unpaid</th>
        <th>Status</th>
        <th width="100" class="col-center">Action</th>
    ',
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4],
        ['data' => 5],
        ['data' => 6],
        ['data' => 7],
        ['data' => 8, 'orderable' => false, 'searchable' => false, 'className' => 'col-center text-center'],
    ],
    'order' => [[0, 'desc']], // order by id desc
    'page_length' => 25,
    'messages' => [
        'success' => $success_message ?? '',
        'error' => $error_message ?? ''
    ]
];



include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
?>
