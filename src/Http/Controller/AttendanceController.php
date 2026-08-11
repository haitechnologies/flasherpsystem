<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Core\DB;
use App\Http\Request;
use App\Http\Response;
use App\Service\AttendanceService;
use App\Exception\ValidationException;

class AttendanceController extends BaseController
{
    private AttendanceService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        AttendanceService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('attendance', 'Attendance');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('attendance.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_attendance' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_attendance' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'employee_id' => (int)$request->post('employee_id', 0),
                'work_date' => $request->post('work_date', ''),
                'check_in' => $request->post('check_in', ''),
                'check_out' => $request->post('check_out', ''),
                'total_hours' => (float)$request->post('total_hours', 0),
                'status' => $request->post('status', 'present'),
            ], $this->userId);
            flash_success('Attendance updated successfully.');
            return Response::redirect('listing_attendance.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("attendance.php?id=$id&action=edit_attendance");
        } catch (\Throwable $e) {
            $this->logError("AttendanceController update error: " . $e->getMessage());
            flash_error($e->getMessage());
            return Response::redirect("attendance.php?id=$id&action=edit_attendance");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'employee_id' => (int)$request->post('employee_id', 0),
                'work_date' => $request->post('work_date', ''),
                'check_in' => $request->post('check_in', ''),
                'check_out' => $request->post('check_out', ''),
                'total_hours' => (float)$request->post('total_hours', 0),
                'status' => $request->post('status', 'present'),
            ], $this->userId);
            flash_success('Attendance saved successfully.');
            return Response::redirect('listing_attendance.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("attendance.php");
        } catch (\Throwable $e) {
            $this->logError("AttendanceController create error: " . $e->getMessage());
            flash_error($e->getMessage());
            return Response::redirect("attendance.php");
        }
    }

    private function showForm(int $id): Response
    {
        $employeeId = 0;
        $workDate = '';
        $checkIn = '';
        $checkOut = '';
        $totalHours = 0;
        $status = 'present';

        $employees = $this->db->fetchAll(
            "SELECT id, full_name FROM `" . DB::USERS . "` WHERE is_active = 1 AND id > 1 ORDER BY full_name ASC"
        );

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_attendance.php');
            }
            $employeeId = $item->employeeId;
            $workDate = $item->workDate;
            $checkIn = $item->checkIn;
            $checkOut = $item->checkOut;
            $totalHours = $item->totalHours;
            $status = $item->status;
        }

        return Response::html($this->view->render('attendance/form.php', [
            'id' => $id,
            'employeeId' => $employeeId,
            'employees' => $employees,
            'workDate' => $workDate,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'totalHours' => $totalHours,
            'status' => $status,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'attendance',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
