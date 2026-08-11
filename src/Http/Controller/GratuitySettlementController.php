<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Core\DB;
use App\Http\Request;
use App\Http\Response;
use App\Service\GratuitySettlementService;
use App\Exception\ValidationException;

class GratuitySettlementController extends BaseController
{
    private GratuitySettlementService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        GratuitySettlementService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('gratuity_settlements', 'Gratuity Settlement');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_gratuity_settlements.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        // AJAX calculate endpoint
        if ($request->isPost() && $action === 'calculate_gratuity') {
            return $this->handleCalculate($request);
        }

        return match (true) {
            $request->isPost() && $action === 'update_gratuity_settlements' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_gratuity_settlements' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $data = [
            'employee_id' => (int)$request->post('employee_id', 0),
            'total_tenure_years' => (float)$request->post('total_tenure_years', 0),
            'total_tenure_days' => (int)$request->post('total_tenure_days', 0),
            'last_basic_salary' => (float)$request->post('last_basic_salary', 0),
            'gratuity_amount' => (float)$request->post('gratuity_amount', 0),
            'status' => $request->post('status', 'calculated'),
            'settlement_date' => $request->post('settlement_date', '') ?: null,
            'payment_date' => $request->post('payment_date', '') ?: null,
            'payment_reference' => $request->post('payment_reference', ''),
            'notes' => $request->post('notes', ''),
        ];

        try {
            $this->service->update($id, $data, $this->userId);
            flash_success('Gratuity settlement updated successfully.');
            return Response::redirect('listing_gratuity_settlements.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("gratuity_settlements.php?id=$id&action=edit_gratuity_settlements");
        } catch (\Throwable $e) {
            $this->logError("GratuitySettlementController::handleUpdate error: " . $e->getMessage());
            flash_error('The gratuity settlement could not be updated.');
            return Response::redirect("gratuity_settlements.php?id=$id&action=edit_gratuity_settlements");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $data = [
            'employee_id' => (int)$request->post('employee_id', 0),
            'total_tenure_years' => (float)$request->post('total_tenure_years', 0),
            'total_tenure_days' => (int)$request->post('total_tenure_days', 0),
            'last_basic_salary' => (float)$request->post('last_basic_salary', 0),
            'gratuity_amount' => (float)$request->post('gratuity_amount', 0),
            'settlement_date' => $request->post('settlement_date', '') ?: null,
            'payment_date' => $request->post('payment_date', '') ?: null,
            'payment_reference' => $request->post('payment_reference', ''),
            'notes' => $request->post('notes', ''),
        ];

        try {
            $this->service->create($data, $this->userId);
            flash_success('Gratuity settlement saved successfully.');
            return Response::redirect('listing_gratuity_settlements.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect('gratuity_settlements.php');
        } catch (\Throwable $e) {
            $this->logError("GratuitySettlementController::handleCreate error: " . $e->getMessage());
            flash_error('The gratuity settlement could not be saved.');
            return Response::redirect('gratuity_settlements.php');
        }
    }

    /**
     * AJAX endpoint to calculate gratuity for a given employee
     */
    private function handleCalculate(Request $request): Response
    {
        $employeeId = (int)$request->post('employee_id', 0);

        if ($employeeId <= 0) {
            return Response::json(['success' => false, 'error' => 'Invalid employee ID.']);
        }

        try {
            $result = $this->service->calculateGratuity($employeeId);
            return Response::json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return Response::json(['success' => false, 'error' => current($e->getErrors())]);
        } catch (\Throwable $e) {
            $this->logError("GratuitySettlementController::handleCalculate error: " . $e->getMessage());
            return Response::json(['success' => false, 'error' => 'Calculation failed: ' . $e->getMessage()]);
        }
    }

    private function showForm(int $id): Response
    {
        $employeeId = 0;
        $totalTenureYears = 0;
        $totalTenureDays = 0;
        $lastBasicSalary = 0;
        $gratuityAmount = 0;
        $status = 'calculated';
        $settlementDate = '';
        $paymentDate = '';
        $paymentReference = '';
        $notes = '';
        $errorMessage = '';

        // Fetch active employees
        $employees = $this->db->fetchAll(
            "SELECT id, full_name FROM `" . DB::USERS . "` WHERE is_active = 1 AND id > 1 ORDER BY full_name ASC"
        );

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_gratuity_settlements.php');
            }
            $employeeId = $item->employeeId;
            $totalTenureYears = $item->totalTenureYears;
            $totalTenureDays = $item->totalTenureDays;
            $lastBasicSalary = $item->lastBasicSalary;
            $gratuityAmount = $item->gratuityAmount;
            $status = $item->status;
            $settlementDate = $item->settlementDate ?? '';
            $paymentDate = $item->paymentDate ?? '';
            $paymentReference = $item->paymentReference;
            $notes = $item->notes;
        }

        return Response::html($this->view->render('gratuity_settlements/form.php', [
            'id' => $id,
            'employeeId' => $employeeId,
            'employees' => $employees,
            'totalTenureYears' => $totalTenureYears,
            'totalTenureDays' => $totalTenureDays,
            'lastBasicSalary' => $lastBasicSalary,
            'gratuityAmount' => $gratuityAmount,
            'status' => $status,
            'settlementDate' => $settlementDate,
            'paymentDate' => $paymentDate,
            'paymentReference' => $paymentReference,
            'notes' => $notes,
            'errorMessage' => $errorMessage,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'gratuity_settlements',
            'moduleId' => $this->moduleId,
            'session_user_id' => $this->userId,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
