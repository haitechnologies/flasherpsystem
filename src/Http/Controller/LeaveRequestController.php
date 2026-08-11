<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\LeaveRequestService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class LeaveRequestController extends BaseController
{
    private LeaveRequestService $leaveRequestService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        LeaveRequestService $leaveRequestService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->leaveRequestService = $leaveRequestService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('leave_requests', 'Leave Request');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_leave_requests.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_leave_requests' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_leave_requests' && $this->canCreate()
                => $this->handleCreate($request),
            $request->isPost() && $action === 'delete_medical_report' && $id > 0 && $this->canEdit()
                => $this->handleDeleteMedicalReport($id),
            default => $this->showForm($id),
        };
    }

    private function convertDateFromDMY(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }
        $dt = \DateTime::createFromFormat('d-m-Y', $date);
        if ($dt !== false) {
            return $dt->format('Y-m-d');
        }
        $parts = explode('-', $date);
        if (count($parts) === 3 && checkdate((int)$parts[1], (int)$parts[0], (int)$parts[2])) {
            return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
        return $date;
    }

    private function saveFormDataToSession(Request $request): void
    {
        $_SESSION['_leave_form_data'] = [
            'employee_id' => $request->getInt('employee_id'),
            'leave_type_id' => $request->getInt('leave_type_id'),
            'start_date' => $request->getString('start_date'),
            'end_date' => $request->getString('end_date'),
            'total_days' => $request->getString('total_days'),
            'reason' => $request->getString('reason'),
            'status' => $request->getString('status', 'pending'),
        ];
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $leaveTypeId = $request->getInt('leave_type_id');
        $sickLeaveId = $this->getSickLeaveTypeId();
        $isSickLeave = $sickLeaveId > 0 && $leaveTypeId === $sickLeaveId;

        $data = [
            'employee_id' => $request->getInt('employee_id'),
            'leave_type_id' => $leaveTypeId,
            'start_date' => $this->convertDateFromDMY($request->getString('start_date')),
            'end_date' => $this->convertDateFromDMY($request->getString('end_date')),
            'total_days' => $request->getString('total_days'),
            'reason' => $request->getString('reason'),
            'status' => $request->getString('status', 'pending'),
        ];

        $existing = $this->leaveRequestService->getById($id, $this->orgId);
        $uploadedFile = $request->getFile('medical_report_file');

        if ($uploadedFile !== null && $uploadedFile['error'] === UPLOAD_ERR_OK) {
            $data['medical_report_file'] = $this->handleMedicalReportUpload($uploadedFile);
        } elseif ($isSickLeave && empty($existing->medicalReportFile)) {
            $this->saveFormDataToSession($request);
            flash_error('Medical Certificate is mandatory for Sick Leave.');
            return Response::redirect("leave_requests.php?id=$id&action=edit_leave_requests");
        }

        try {
            $this->leaveRequestService->update($id, $data, $this->orgId);
            unset($_SESSION['_leave_form_data']);
            flash_success('The Leave Request has been updated successfully.');
            return Response::redirect('listing_leave_requests.php');
        } catch (ValidationException $e) {
            $this->saveFormDataToSession($request);
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("leave_requests.php?id=$id&action=edit_leave_requests");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("leave_requests.php?id=$id&action=edit_leave_requests");
        } catch (\Throwable $e) {
            $this->logError("LeaveRequestController::handleUpdate error: " . $e->getMessage());
            flash_error('The Leave Request could not be updated.');
            return Response::redirect("leave_requests.php?id=$id&action=edit_leave_requests");
        }
    }

    private function getSickLeaveTypeId(): int
    {
        try {
            $types = \App\Core\Container::getInstance()->get(\App\Service\LeaveTypeService::class)->list($this->orgId);
            foreach ($types as $t) {
                if ($t->leaveType === 'Sick Leave') {
                    return $t->id;
                }
            }
        } catch (\Throwable $e) {
            $this->logError("LeaveRequestController::getSickLeaveTypeId error: " . $e->getMessage());
        }
        return 0;
    }

    private function handleCreate(Request $request): Response
    {
        $leaveTypeId = $request->getInt('leave_type_id');
        $sickLeaveId = $this->getSickLeaveTypeId();
        $isSickLeave = $sickLeaveId > 0 && $leaveTypeId === $sickLeaveId;

        $uploadedFile = $request->getFile('medical_report_file');
        if ($isSickLeave && ($uploadedFile === null || $uploadedFile['error'] !== UPLOAD_ERR_OK)) {
            $this->saveFormDataToSession($request);
            flash_error('Medical Certificate is mandatory for Sick Leave.');
            return Response::redirect("leave_requests.php");
        }

        $data = [
            'employee_id' => $request->getInt('employee_id'),
            'leave_type_id' => $leaveTypeId,
            'start_date' => $this->convertDateFromDMY($request->getString('start_date')),
            'end_date' => $this->convertDateFromDMY($request->getString('end_date')),
            'total_days' => (float)$request->getString('total_days'),
            'reason' => $request->getString('reason'),
            'status' => $request->getString('status', 'pending'),
        ];

        if ($uploadedFile !== null && $uploadedFile['error'] === UPLOAD_ERR_OK) {
            $data['medical_report_file'] = $this->handleMedicalReportUpload($uploadedFile);
        }

        try {
            $this->leaveRequestService->create($data, $this->orgId);
            unset($_SESSION['_leave_form_data']);
            flash_success('The Leave Request has been saved successfully.');
            return Response::redirect('listing_leave_requests.php');
        } catch (ValidationException $e) {
            $this->saveFormDataToSession($request);
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("leave_requests.php");
        } catch (\Throwable $e) {
            $this->logError("LeaveRequestController::handleCreate error: " . $e->getMessage());
            flash_error('The Leave Request could not be saved.');
            return Response::redirect("leave_requests.php");
        }
    }

    private function showForm(int $id): Response
    {
        $employeeId = 0;
        $leaveTypeId = 0;
        $startDate = '';
        $endDate = '';
        $totalDays = 0.0;
        $reason = '';
        $status = 'pending';
        $medicalReportFile = null;
        $error_message = '';
        $moduleCaption = $this->moduleCaption;
        $module = 'leave_requests';
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;

        // Restore submitted form data from session on validation errors
        if (isset($_SESSION['_leave_form_data'])) {
            $fd = $_SESSION['_leave_form_data'];
            $employeeId = (int)($fd['employee_id'] ?? 0);
            $leaveTypeId = (int)($fd['leave_type_id'] ?? 0);
            $startDate = $fd['start_date'] ?? '';
            $endDate = $fd['end_date'] ?? '';
            $totalDays = (float)($fd['total_days'] ?? 0);
            $reason = $fd['reason'] ?? '';
            $status = $fd['status'] ?? 'pending';
            unset($_SESSION['_leave_form_data']);
        } elseif ($id > 0) {
            try {
                $req = $this->leaveRequestService->getById($id, $this->orgId);
                $employeeId = $req->employeeId;
                $leaveTypeId = $req->leaveTypeId;
                $startDate = $req->startDate !== '' && $req->startDate !== '1970-01-01' ? date('d-m-Y', strtotime($req->startDate)) : '';
                $endDate = $req->endDate !== '' && $req->endDate !== '1970-01-01' ? date('d-m-Y', strtotime($req->endDate)) : '';
                $totalDays = $req->totalDays;
                $reason = $req->reason ?? '';
                $status = $req->status;
                $medicalReportFile = $req->medicalReportFile;
            } catch (NotFoundException $e) {
                $error_message = $e->getMessage();
            }
        }

        $users = \App\Core\Container::getInstance()->get(\App\Repository\UserRepository::class)->findAll();
        $leaveTypes = \App\Core\Container::getInstance()->get(\App\Service\LeaveTypeService::class)->list($this->orgId);

        $this->cleanupOldMedicalReports();
        $uploadPath = __DIR__ . '/../../../uploads/leave_requests/';

        return Response::html($this->view->render('leave_requests/form.php', [
            'id' => $id,
            'employeeId' => $employeeId,
            'leaveTypeId' => $leaveTypeId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalDays' => $totalDays,
            'reason' => $reason,
            'status' => $status,
            'error_message' => $error_message,
            'moduleCaption' => $moduleCaption,
            'module' => $module,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'medicalReportFile' => $medicalReportFile,
            'sickLeaveTypeId' => $this->getSickLeaveTypeId(),
            'uploadPath' => '../uploads/leave_requests/',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
            'users' => $users,
            'leaveTypes' => $leaveTypes,
        ]));
    }

    private function handleMedicalReportUpload(array $file): string
    {
        $uploadDir = __DIR__ . '/../../../uploads/leave_requests/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        if (!in_array($ext, $allowed, true)) {
            throw new ValidationException(['medical_report_file' => 'Only PDF, DOC, DOCX, JPG, PNG files are allowed.']);
        }

        $filename = 'medical_report_' . uniqid() . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException('Failed to upload medical report file.');
        }

        return $filename;
    }

    private function handleDeleteMedicalReport(int $id): Response
    {
        try {
            $req = $this->leaveRequestService->getById($id, $this->orgId);
        } catch (NotFoundException $e) {
            flash_error('Leave request not found.');
            return Response::redirect('listing_leave_requests.php');
        }

        if (!empty($req->medicalReportFile)) {
            $filePath = __DIR__ . '/../../../uploads/leave_requests/' . $req->medicalReportFile;
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        $this->db->execute(
            "UPDATE `" . \App\Core\DB::LEAVE_REQUESTS . "` SET medical_report_file = NULL, medical_report_provided = 0 WHERE id = :id AND organization_id = :org_id",
            ['id' => $id, 'org_id' => $this->orgId]
        );

        flash_success('Medical certificate has been deleted.');
        return Response::redirect("leave_requests.php?id=$id");
    }

    private function cleanupOldMedicalReports(): void
    {
        $maxAgeDays = 90;
        $uploadDir = __DIR__ . '/../../../uploads/leave_requests/';
        if (!is_dir($uploadDir)) {
            return;
        }
        $cutoff = time() - ($maxAgeDays * 86400);
        $files = glob($uploadDir . 'medical_report_*');
        if ($files === false) {
            return;
        }
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}
