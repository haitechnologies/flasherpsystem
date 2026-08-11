<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\AnnualLeaveEntitlementService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class AnnualLeaveEntitlementController extends BaseController
{
    private AnnualLeaveEntitlementService $annualLeaveEntitlementService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        AnnualLeaveEntitlementService $annualLeaveEntitlementService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->annualLeaveEntitlementService = $annualLeaveEntitlementService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('annual_leave_entitlements', 'Annual Leave Entitlement');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_annual_leave_entitlements.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_annual_leave_entitlements' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_annual_leave_entitlements' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($id, $request),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $data = [
            'leave_availed' => $request->get('leave_availed', 0),
            'air_ticket_availed' => $request->getString('air_ticket_availed'),
            'status' => $request->getString('status'),
            'notes' => $request->getString('notes'),
        ];

        try {
            $this->annualLeaveEntitlementService->update($id, $data, $this->userId, $this->orgId);
            flash_success('The Annual Leave Entitlement has been updated successfully.');
            return Response::redirect('listing_annual_leave_entitlements.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("annual_leave_entitlements.php?id=$id&action=edit_annual_leave_entitlements");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("annual_leave_entitlements.php?id=$id&action=edit_annual_leave_entitlements");
        } catch (\Throwable $e) {
            $this->logError("AnnualLeaveEntitlementController::handleUpdate error: " . $e->getMessage());
            flash_error('The Annual Leave Entitlement could not be updated.');
            return Response::redirect("annual_leave_entitlements.php?id=$id&action=edit_annual_leave_entitlements");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $data = [
            'employee_id' => $request->getInt('employee_id'),
            'entitlement_year' => $request->getInt('entitlement_year'),
            'total_leave_days' => $request->get('total_leave_days', 30),
            'leave_availed' => $request->get('leave_availed', 0),
            'leave_balance' => $request->get('leave_balance', 30),
            'air_ticket_amount' => $request->get('air_ticket_amount', 1250.00),
            'air_ticket_availed' => $request->getString('air_ticket_availed'),
            'status' => $request->getString('status', 'active'),
            'notes' => $request->getString('notes'),
        ];

        try {
            $this->annualLeaveEntitlementService->create($data, $this->userId, $this->orgId);
            flash_success('The Annual Leave Entitlement has been saved successfully.');
            return Response::redirect('listing_annual_leave_entitlements.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect('annual_leave_entitlements.php');
        } catch (\Throwable $e) {
            $this->logError("AnnualLeaveEntitlementController::handleCreate error: " . $e->getMessage());
            flash_error('The Annual Leave Entitlement could not be saved.');
            return Response::redirect('annual_leave_entitlements.php');
        }
    }

    private function showForm(int $id, Request $request): Response
    {
        $employeeId = 0;
        $entitlementYear = (int)date('Y');
        $totalLeaveDays = 30;
        $leaveAvailed = 0;
        $leaveBalance = 30;
        $airTicketAmount = 1250.00;
        $airTicketAvailed = '';
        $status = 'active';
        $notes = '';
        $errorMessage = '';
        $moduleCaption = $this->moduleCaption;
        $module = 'annual_leave_entitlements';
        $moduleId = $this->moduleId;
        $sessionUserId = $this->userId;

        if ($id > 0) {
            try {
                $entitlement = $this->annualLeaveEntitlementService->getById($id, $this->orgId);
                $employeeId = $entitlement->employeeId;
                $entitlementYear = $entitlement->entitlementYear;
                $totalLeaveDays = $entitlement->totalLeaveDays;
                $leaveAvailed = $entitlement->leaveAvailed;
                $leaveBalance = $entitlement->leaveBalance;
                $airTicketAmount = $entitlement->airTicketAmount;
                $airTicketAvailed = $entitlement->airTicketAvailed;
                $status = $entitlement->status;
                $notes = $entitlement->notes;
            } catch (NotFoundException $e) {
                $errorMessage = $e->getMessage();
            }
        }

        $employees = $this->db->fetchAll(
            "SELECT id, full_name FROM " . \App\Core\DB::USERS . "
             WHERE organization_id = :org_id AND is_active = 1
             ORDER BY full_name ASC",
            ['org_id' => $this->orgId]
        );

        $departments = $this->db->fetchAll(
            "SELECT id, department AS name FROM " . \App\Core\DB::DEPARTMENTS . "
             WHERE organization_id = :org_id AND publish = 1
             ORDER BY department ASC",
            ['org_id' => $this->orgId]
        );

        $designations = $this->db->fetchAll(
            "SELECT id, designation AS name FROM " . \App\Core\DB::DESIGNATIONS . "
             WHERE organization_id = :org_id AND publish = 1
             ORDER BY designation ASC",
            ['org_id' => $this->orgId]
        );

        return Response::html($this->view->render('annual_leave_entitlements/form.php', [
            'id' => $id,
            'employeeId' => $employeeId,
            'entitlementYear' => $entitlementYear,
            'totalLeaveDays' => $totalLeaveDays,
            'leaveAvailed' => $leaveAvailed,
            'leaveBalance' => $leaveBalance,
            'airTicketAmount' => $airTicketAmount,
            'airTicketAvailed' => $airTicketAvailed,
            'status' => $status,
            'notes' => $notes,
            'errorMessage' => $errorMessage,
            'moduleCaption' => $moduleCaption,
            'module' => $module,
            'moduleId' => $moduleId,
            'sessionUserId' => $sessionUserId,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
            'employees' => $employees,
            'departments' => $departments,
            'designations' => $designations,
        ]));
    }
}
