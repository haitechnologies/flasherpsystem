<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Container;
use App\Service\AirTicketService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

include('admin_elements/admin_header.php');

$module = 'air_tickets';
$module_caption = 'Air Tickets';
$tbl_name = DB::AIR_TICKETS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$container = Container::getInstance();
/** @var AirTicketService $airTicketService */
$airTicketService = $container->get(AirTicketService::class);

if (($action == "delete_$module" && !empty($id))) {
    try {
        $airTicketService->delete((int)$id, $activeOrganizationId);
        $success_message = "Air Ticket deleted successfully.";
        flash_success($success_message);
        header("Location:listing_$module.php");
        exit;
    } catch (ValidationException $e) {
        $error_message = current($e->getErrors());
    } catch (NotFoundException $e) {
        $error_message = $e->getMessage();
    } catch (\Throwable $e) {
        $error_message = "Air Ticket could not be deleted.";
        log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'air_tickets', 'action' => 'delete']);
    }
}

// Handle bulk generate if triggered via GET
if (isset($_GET['action']) && $_GET['action'] === 'generate' && granted_('create', $module)) {
    try {
        $eligibleEmployees = $airTicketService->calculateEligibleEmployees($activeOrganizationId);
        $count = 0;
        foreach ($eligibleEmployees as $employee) {
            $data = [
                'employee_id' => (int)$employee['id'],
                'entitlement_amount' => 1250.00,
                'status' => 'pending',
            ];
            $airTicketService->create($data, (int)$_SESSION[$GLOBALS['project_pre']]['DASHBOARD']['user_id'], $activeOrganizationId);
            $count++;
        }

        if ($count > 0) {
            $success_message = "{$count} Air Ticket(s) generated successfully for eligible employees.";
            flash_success($success_message);
        } else {
            flash_info('No eligible employees found for air ticket generation.');
        }
    } catch (\Throwable $e) {
        $error_message = 'Air Ticket generation failed: ' . $e->getMessage();
        log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'air_tickets', 'action' => 'generate']);
    }

    header("Location:listing_$module.php");
    exit;
}

$canCreate = granted_('create', $module);

$generateButton = '';
if ($canCreate) {
    $generateButton = '<a href="air_tickets.php?action=generate" class="btn btn-success btn-sm d-inline-flex align-items-center me-2">
        <i class="ph-lightning ph-sm me-2 opacity-75"></i>Generate Tickets
    </a>';
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'hide_add_button' => false,
    'thead' => '
        <th width="50">SR.</th>
        <th>Employee</th>
        <th>Entitlement</th>
        <th>Status</th>
        <th>Eligibility Date</th>
        <th>Paid Date</th>
        <th width="100" class="col-center">Action</th>
    ',
    'columns' => [
        ['data' => 0, 'orderable' => false, 'searchable' => false],
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
    ],
    'before_table' => $generateButton ? '<div class="mb-3">' . $generateButton . '</div>' : '',
];



include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
