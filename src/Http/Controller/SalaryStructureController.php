<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\SalaryStructureService;
use App\Exception\ValidationException;

class SalaryStructureController extends BaseController
{
    private SalaryStructureService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        SalaryStructureService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('salary_structures', 'Salary Structure');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_salary_structures.php');
        }

        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'batch_save' && $this->canEdit()
            => $this->handleBatchSave($request),
            $request->isPost() && $action === 'add_salary_structures' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($request),
        };
    }

    private function convertDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }
        $dt = \DateTime::createFromFormat('d-m-Y', $date);
        if ($dt !== false) {
            return $dt->format('Y-m-d');
        }
        return $date;
    }

    private function handleBatchSave(Request $request): Response
    {
        $employeeId = $request->getInt('employee_id');
        $rawComponents = $request->get('components', []);
        $components = [];

        foreach ($rawComponents as $componentId => $data) {
            $components[$componentId] = [
                'amount' => $data['amount'] ?? '0',
                'effective_from' => $this->convertDate($data['effective_from'] ?? ''),
                'effective_to' => $this->convertDate($data['effective_to'] ?? ''),
            ];
        }

        try {
            $this->service->saveBatch($employeeId, $components, $this->orgId, $this->userId);
            flash_success('Salary structure has been saved successfully.');
            return Response::redirect('listing_salary_structures.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("salary_structures.php?employee_id=$employeeId");
        } catch (\Throwable $e) {
            $this->logError("SalaryStructureController::handleBatchSave error: " . $e->getMessage());
            flash_error('Salary structure could not be saved.');
            return Response::redirect('listing_salary_structures.php');
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->saveBatch(
                $request->getInt('employee_id'),
                [
                    $request->getInt('component_id') => [
                        'amount' => $request->getString('amount'),
                        'effective_from' => $this->convertDate($request->getString('effective_from')),
                        'effective_to' => $this->convertDate($request->getString('effective_to')),
                    ],
                ],
                $this->orgId,
                $this->userId
            );
            flash_success('Salary component has been added successfully.');
            return Response::redirect('listing_salary_structures.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("salary_structures.php?employee_id=" . $request->getInt('employee_id'));
        } catch (\Throwable $e) {
            $this->logError("SalaryStructureController::handleCreate error: " . $e->getMessage());
            flash_error('Salary component could not be saved.');
            return Response::redirect('listing_salary_structures.php');
        }
    }

    private function showForm(Request $request): Response
    {
        $employeeId = $request->getInt('employee_id');
        $existing = [];
        $dateOfJoining = '';

        if ($employeeId > 0) {
            $existing = $this->service->getByEmployeeIndexed($employeeId, $this->orgId);
            $empRow = $this->db->fetchOne("SELECT date_of_joining FROM `" . DB::USERS . "` WHERE id = :id", ['id' => $employeeId]);
            if ($empRow && !empty($empRow['date_of_joining'])) {
                $rawDoj = $empRow['date_of_joining'];
                if ($rawDoj !== '1970-01-01' && $rawDoj !== '0000-00-00') {
                    $dateOfJoining = date('d-m-Y', strtotime($rawDoj));
                }
            }
        }

        $employees = $this->db->fetchAll("SELECT id, full_name FROM `" . DB::USERS . "` WHERE is_active=1 AND id>1 ORDER BY full_name");
        $allComponents = $this->db->fetchAll("SELECT id, component_name, component_type FROM `" . DB::PAYROLL_COMPONENTS . "` ORDER BY id");
        $earningComponents = [];
        $deductionComponents = [];
        foreach ($allComponents as $c) {
            if (($c['component_type'] ?? '') === 'deduction') {
                $deductionComponents[] = $c;
            } else {
                $earningComponents[] = $c;
            }
        }

        return Response::html($this->view->render('salary_structures/form.php', [
            'employeeId' => $employeeId,
            'existing' => $existing,
            'dateOfJoining' => $dateOfJoining,
            'earningComponents' => $earningComponents,
            'deductionComponents' => $deductionComponents,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'salary_structures',
            'moduleId' => $this->moduleId,
            'session_user_id' => $this->userId,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
            'employees' => $employees,
        ]));
    }
}
