<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Container;
use App\Service\AnnualLeaveEntitlementService;

use App\Exception\ValidationException;
use App\Exception\NotFoundException;

include('admin_elements/admin_header.php');

$module = 'annual_leave_entitlements';
$module_caption = 'Annual Leave Entitlements';
$tbl_name = DB::ANNUAL_LEAVE_ENTITLEMENTS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$container = Container::getInstance();
/** @var AnnualLeaveEntitlementService $annualLeaveEntitlementService */
$annualLeaveEntitlementService = $container->get(AnnualLeaveEntitlementService::class);

if (($action == "delete_$module" && !empty($id))) {
    try {
        $annualLeaveEntitlementService->delete((int)$id, $activeOrganizationId);
        $success_message = "Annual Leave Entitlement deleted successfully.";
        flash_success($success_message);
        header("Location:listing_$module.php");
        exit;
    } catch (ValidationException $e) {
        $error_message = current($e->getErrors());
    } catch (NotFoundException $e) {
        $error_message = $e->getMessage();
    } catch (\Throwable $e) {
        $error_message = "Annual Leave Entitlement could not be deleted.";
        log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'annual_leave_entitlements', 'action' => 'delete']);
    }
}

$canCreate = granted_('create', $module);

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'hide_add_button' => false,
    'thead' => '
        <th width="50">SR.</th>
        <th>Employee</th>
        <th>Year</th>
        <th>Total Days</th>
        <th>Availed</th>
        <th>Balance</th>
        <th>Air Ticket</th>
        <th>Status</th>
        <th width="100" class="col-center">Action</th>
    ',
    'columns' => [
        ['data' => 0, 'orderable' => false, 'searchable' => false],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4],
        ['data' => 5],
        ['data' => 6],
        ['data' => 7],
        ['data' => 8, 'orderable' => false, 'searchable' => false, 'className' => 'col-center text-center'],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
    'messages' => [
        'success' => $success_message ?? '',
        'error' => $error_message ?? ''
    ],
];



include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
