<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\AlertService;
use App\Exception\ValidationException;

class AlertController extends BaseController
{
    private AlertService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        AlertService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('alert_names', 'Alert');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('alert_names.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_alert_names' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_alert_names' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'alert_name' => $request->post('alert_name', ''),
                'description' => $request->post('description', ''),
                'type' => $request->post('type', 'general'),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Alert has been updated successfully.');
            return Response::redirect('listing_alert_names.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("alert_names.php?id=$id&action=edit_alert_names");
        } catch (\Throwable $e) {
            $this->logError("AlertController::handleUpdate error: " . $e->getMessage());
            flash_error('The Alert could not be updated.');
            return Response::redirect("alert_names.php?id=$id&action=edit_alert_names");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'alert_name' => $request->post('alert_name', ''),
                'description' => $request->post('description', ''),
                'type' => $request->post('type', 'general'),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Alert has been saved successfully.');
            return Response::redirect('listing_alert_names.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("alert_names.php");
        } catch (\Throwable $e) {
            $this->logError("AlertController::handleCreate error: " . $e->getMessage());
            flash_error('The Alert could not be saved.');
            return Response::redirect("alert_names.php");
        }
    }

    private function showForm(int $id): Response
    {
        $alertName = '';
        $description = '';
        $type = 'general';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_alert_names.php');
            }
            $alertName = $item->alertName;
            $description = $item->description;
            $type = $item->type;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('alerts/form.php', [
            'id' => $id,
            'alertName' => $alertName,
            'description' => $description,
            'type' => $type,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'alert_names',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
