<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\ServiceService;
use App\Exception\ValidationException;

class ServiceController extends BaseController
{
    private ServiceService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        ServiceService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('service_names', 'Service');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('service_names.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_service_names' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_service_names' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'service_name' => $request->post('service_name', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Service has been updated successfully.');
            return Response::redirect('listing_service_names.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("service_names.php?id=$id&action=edit_service_names");
        } catch (\Throwable $e) {
            $this->logError("ServiceController::handleUpdate error: " . $e->getMessage());
            flash_error('The Service could not be updated.');
            return Response::redirect("service_names.php?id=$id&action=edit_service_names");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'service_name' => $request->post('service_name', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Service has been saved successfully.');
            return Response::redirect('listing_service_names.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("service_names.php");
        } catch (\Throwable $e) {
            $this->logError("ServiceController::handleCreate error: " . $e->getMessage());
            flash_error('The Service could not be saved.');
            return Response::redirect("service_names.php");
        }
    }

    private function showForm(int $id): Response
    {
        $serviceName = '';
        $description = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_service_names.php');
            }
            $serviceName = $item->serviceName;
            $description = $item->description;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('service_names/form.php', [
            'id' => $id,
            'serviceName' => $serviceName,
            'description' => $description,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'service_names',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
