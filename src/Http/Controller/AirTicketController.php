<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\AirTicketService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class AirTicketController extends BaseController
{
    private AirTicketService $airTicketService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        AirTicketService $airTicketService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->airTicketService = $airTicketService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('air_tickets', 'Air Ticket');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_air_tickets.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_air_ticket' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_air_ticket' && $this->canCreate()
                => $this->handleCreate($request),
            $request->isPost() && $action === 'bulk_generate' && $this->canCreate()
                => $this->handleBulkGenerate($request),
            default => $this->showForm($id, $request),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $data = [
            'status' => $request->getString('status'),
            'paid_date' => $request->getString('paid_date') ?: null,
            'payment_reference' => $request->getString('payment_reference'),
            'notes' => $request->getString('notes'),
        ];

        try {
            $this->airTicketService->update($id, $data, $this->userId, $this->orgId);
            flash_success('The Air Ticket has been updated successfully.');
            return Response::redirect('listing_air_tickets.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("air_tickets.php?id=$id&action=edit_air_ticket");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("air_tickets.php?id=$id&action=edit_air_ticket");
        } catch (\Throwable $e) {
            $this->logError("AirTicketController::handleUpdate error: " . $e->getMessage());
            flash_error('The Air Ticket could not be updated.');
            return Response::redirect("air_tickets.php?id=$id&action=edit_air_ticket");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $data = [
            'employee_id' => $request->getInt('employee_id'),
            'entitlement_amount' => $request->get('entitlement_amount', 1250.00),
            'status' => $request->getString('status', 'pending'),
            'eligibility_date' => $request->getString('eligibility_date') ?: null,
            'paid_date' => $request->getString('paid_date') ?: null,
            'payment_reference' => $request->getString('payment_reference'),
            'notes' => $request->getString('notes'),
        ];

        try {
            $this->airTicketService->create($data, $this->userId, $this->orgId);
            flash_success('The Air Ticket has been saved successfully.');
            return Response::redirect('listing_air_tickets.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect('air_tickets.php');
        } catch (\Throwable $e) {
            $this->logError("AirTicketController::handleCreate error: " . $e->getMessage());
            flash_error('The Air Ticket could not be saved.');
            return Response::redirect('air_tickets.php');
        }
    }

    private function handleBulkGenerate(Request $request): Response
    {
        try {
            $eligibleEmployees = $this->airTicketService->calculateEligibleEmployees($this->orgId);
            $count = 0;
            foreach ($eligibleEmployees as $employee) {
                $data = [
                    'employee_id' => (int)$employee['id'],
                    'entitlement_amount' => 1250.00,
                    'status' => 'pending',
                ];
                $this->airTicketService->create($data, $this->userId, $this->orgId);
                $count++;
            }

            if ($count > 0) {
                flash_success("{$count} Air Ticket(s) generated successfully for eligible employees.");
            } else {
                flash_info('No eligible employees found for air ticket generation.');
            }

            return Response::redirect('listing_air_tickets.php');
        } catch (\Throwable $e) {
            $this->logError("AirTicketController::handleBulkGenerate error: " . $e->getMessage());
            flash_error('Air Ticket generation failed: ' . $e->getMessage());
            return Response::redirect('listing_air_tickets.php');
        }
    }

    private function showForm(int $id, Request $request): Response
    {
        $employeeId = 0;
        $entitlementAmount = 1250.00;
        $status = 'pending';
        $eligibilityDate = '';
        $paidDate = '';
        $paymentReference = '';
        $notes = '';
        $errorMessage = '';
        $moduleCaption = $this->moduleCaption;
        $module = 'air_tickets';
        $moduleId = $this->moduleId;
        $sessionUserId = $this->userId;

        if ($id > 0) {
            try {
                $ticket = $this->airTicketService->getById($id, $this->orgId);
                $employeeId = $ticket->employeeId;
                $entitlementAmount = $ticket->entitlementAmount;
                $status = $ticket->status;
                $eligibilityDate = $ticket->eligibilityDate ?? '';
                $paidDate = $ticket->paidDate ?? '';
                $paymentReference = $ticket->paymentReference;
                $notes = $ticket->notes;
            } catch (NotFoundException $e) {
                $errorMessage = $e->getMessage();
            }
        }

        // Get employees list for dropdown
        $employees = $this->db->fetchAll(
            "SELECT id, full_name FROM " . \App\Core\DB::USERS . " 
             WHERE organization_id = :org_id AND is_active = 1 
             ORDER BY full_name ASC",
            ['org_id' => $this->orgId]
        );

        return Response::html($this->view->render('air_tickets/form.php', [
            'id' => $id,
            'employeeId' => $employeeId,
            'entitlementAmount' => $entitlementAmount,
            'status' => $status,
            'eligibilityDate' => $eligibilityDate,
            'paidDate' => $paidDate,
            'paymentReference' => $paymentReference,
            'notes' => $notes,
            'errorMessage' => $errorMessage,
            'moduleCaption' => $moduleCaption,
            'module' => $module,
            'moduleId' => $moduleId,
            'sessionUserId' => $sessionUserId,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
            'employees' => $employees,
        ]));
    }
}
