<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\UnitService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class UnitController extends BaseController
{
    private UnitService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        UnitService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('units', 'Unit');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_units.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_units' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_units' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $data = [
            'unit_name' => $request->getString('unit'),
            'publish' => $request->get('publish') ? 1 : 0,
        ];

        try {
            $this->service->update($id, $data, $this->userId);
            flash_success('The Unit has been updated successfully.');
            return Response::redirect('listing_units.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("units.php?id=$id&action=edit_units");
        } catch (\Throwable $e) {
            $this->logError("UnitController::handleUpdate error: " . $e->getMessage());
            flash_error('The Unit could not be updated.');
            return Response::redirect("units.php?id=$id&action=edit_units");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $data = [
            'unit_name' => $request->getString('unit'),
            'publish' => $request->get('publish') ? 1 : 0,
        ];

        try {
            $this->service->create($data, $this->userId);
            flash_success('The Unit has been saved successfully.');
            return Response::redirect('listing_units.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("units.php");
        } catch (\Throwable $e) {
            $this->logError("UnitController::handleCreate error: " . $e->getMessage());
            flash_error('The Unit could not be saved.');
            return Response::redirect("units.php");
        }
    }

    private function showForm(int $id): Response
    {
        $unit = '';
        $publish = 1;
        $error_message = '';
        $moduleCaption = $this->moduleCaption;
        $module = 'units';
        $session_user_id = $this->userId;

        if ($id > 0) {
            $model = $this->service->getById($id);
            if ($model !== null) {
                $unit = $model->unitName;
                $publish = $model->isActive ? 1 : 0;
            }
        }

        return Response::html($this->view->render('units/form.php', [
            'id' => $id,
            'unit' => $unit,
            'publish' => $publish,
            'error_message' => $error_message,
            'moduleCaption' => $moduleCaption,
            'module' => $module,
            'session_user_id' => $session_user_id,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
