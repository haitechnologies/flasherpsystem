<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Core\DB;
use App\Http\Request;
use App\Http\Response;
use App\Service\AttendanceDeviceService;
use App\Exception\ValidationException;

class AttendanceDeviceController extends BaseController
{
    private AttendanceDeviceService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        AttendanceDeviceService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('attendance_devices', 'Attendance Devices');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('attendance_devices.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_attendance_device' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_attendance_device' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'device_name' => $request->post('device_name', ''),
                'ip_address' => $request->post('ip_address', ''),
                'port' => (int)$request->post('port', 4370),
                'serial_number' => $request->post('serial_number', ''),
                'device_password' => $request->post('device_password', '0'),
                'device_model' => $request->post('device_model', ''),
                'location' => $request->post('location', ''),
                'is_active' => $request->post('is_active', 0),
            ], $this->userId);
            flash_success('Device updated successfully.');
            return Response::redirect('listing_attendance_devices.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("attendance_devices.php?id=$id&action=edit_attendance_device");
        } catch (\Throwable $e) {
            $this->logError("AttendanceDeviceController update error: " . $e->getMessage());
            flash_error($e->getMessage());
            return Response::redirect("attendance_devices.php?id=$id&action=edit_attendance_device");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'organization_id' => $this->orgId,
                'device_name' => $request->post('device_name', ''),
                'ip_address' => $request->post('ip_address', ''),
                'port' => (int)$request->post('port', 4370),
                'serial_number' => $request->post('serial_number', ''),
                'device_password' => $request->post('device_password', '0'),
                'device_model' => $request->post('device_model', ''),
                'location' => $request->post('location', ''),
                'is_active' => $request->post('is_active', 0),
            ], $this->userId);
            flash_success('Device added successfully.');
            return Response::redirect('listing_attendance_devices.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect('attendance_devices.php');
        } catch (\Throwable $e) {
            $this->logError("AttendanceDeviceController create error: " . $e->getMessage());
            flash_error($e->getMessage());
            return Response::redirect("attendance_devices.php");
        }
    }

    private function showForm(int $id): Response
    {
        $deviceName = '';
        $ipAddress = '';
        $port = 4370;
        $serialNumber = '';
        $devicePassword = '0';
        $deviceModel = '';
        $location = '';
        $isActive = 1;

        if ($id > 0) {
            $device = $this->service->getById($id);
            if ($device === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_attendance_devices.php');
            }
            $deviceName = $device->deviceName;
            $ipAddress = $device->ipAddress;
            $port = $device->port;
            $serialNumber = $device->serialNumber;
            $devicePassword = $device->devicePassword;
            $deviceModel = $device->deviceModel;
            $location = $device->location;
            $isActive = $device->isActive;
        }

        return Response::html($this->view->render('attendance_devices/form.php', [
            'id' => $id,
            'deviceName' => $deviceName,
            'ipAddress' => $ipAddress,
            'port' => $port,
            'serialNumber' => $serialNumber,
            'devicePassword' => $devicePassword,
            'deviceModel' => $deviceModel,
            'location' => $location,
            'isActive' => $isActive,
            'employees' => [],
            'devices' => [],
            'moduleCaption' => $this->moduleCaption,
            'module' => 'attendance_devices',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
