<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\IncotermService;
use App\Exception\ValidationException;

class IncotermController extends BaseController
{
    private IncotermService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        IncotermService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('incoterms', 'Incoterm');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('incoterms.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_incoterms' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_incoterms' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'incoterm' => $request->post('incoterm', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Incoterm has been updated successfully.');
            return Response::redirect('listing_incoterms.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("incoterms.php?id=$id&action=edit_incoterms");
        } catch (\Throwable $e) {
            $this->logError("IncotermController::handleUpdate error: " . $e->getMessage());
            flash_error('The Incoterm could not be updated.');
            return Response::redirect("incoterms.php?id=$id&action=edit_incoterms");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'incoterm' => $request->post('incoterm', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Incoterm has been saved successfully.');
            return Response::redirect('listing_incoterms.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("incoterms.php");
        } catch (\Throwable $e) {
            $this->logError("IncotermController::handleCreate error: " . $e->getMessage());
            flash_error('The Incoterm could not be saved.');
            return Response::redirect("incoterms.php");
        }
    }

    private function showForm(int $id): Response
    {
        $incoterm = '';
        $description = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_incoterms.php');
            }
            $incoterm = $item->incoterm;
            $description = $item->description;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('incoterms/form.php', [
            'id' => $id,
            'incoterm' => $incoterm,
            'description' => $description,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'incoterms',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
