<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\ExitPointService;
use App\Exception\ValidationException;

class ExitPointController extends BaseController
{
    private ExitPointService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        ExitPointService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('exit_points', 'Exit Point');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('exit_points.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_exit_points' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_exit_points' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'exit_point' => $request->post('exit_point', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Exit Point has been updated successfully.');
            return Response::redirect('listing_exit_points.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("exit_points.php?id=$id&action=edit_exit_points");
        } catch (\Throwable $e) {
            $this->logError("ExitPointController::handleUpdate error: " . $e->getMessage());
            flash_error('The Exit Point could not be updated.');
            return Response::redirect("exit_points.php?id=$id&action=edit_exit_points");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'exit_point' => $request->post('exit_point', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Exit Point has been saved successfully.');
            return Response::redirect('listing_exit_points.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("exit_points.php");
        } catch (\Throwable $e) {
            $this->logError("ExitPointController::handleCreate error: " . $e->getMessage());
            flash_error('The Exit Point could not be saved.');
            return Response::redirect("exit_points.php");
        }
    }

    private function showForm(int $id): Response
    {
        $exitPoint = '';
        $description = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_exit_points.php');
            }
            $exitPoint = $item->exitPoint;
            $description = $item->description;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('exit_points/form.php', [
            'id' => $id,
            'exitPoint' => $exitPoint,
            'description' => $description,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'exit_points',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
