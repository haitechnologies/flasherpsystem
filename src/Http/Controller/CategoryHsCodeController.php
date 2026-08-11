<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\CategoryHsCodeService;
use App\Exception\ValidationException;

class CategoryHsCodeController extends BaseController
{
    private CategoryHsCodeService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        CategoryHsCodeService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('notess', 'Category HS Code');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('notess.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_notess' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_notess' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'notes' => $request->post('notes', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Category HS Code has been updated successfully.');
            return Response::redirect('listing_notess.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("notess.php?id=$id&action=edit_notess");
        } catch (\Throwable $e) {
            $this->logError("CategoryHsCodeController::handleUpdate error: " . $e->getMessage());
            flash_error('The Category HS Code could not be updated.');
            return Response::redirect("notess.php?id=$id&action=edit_notess");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'notes' => $request->post('notes', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Category HS Code has been saved successfully.');
            return Response::redirect('listing_notess.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("notess.php");
        } catch (\Throwable $e) {
            $this->logError("CategoryHsCodeController::handleCreate error: " . $e->getMessage());
            flash_error('The Category HS Code could not be saved.');
            return Response::redirect("notess.php");
        }
    }

    private function showForm(int $id): Response
    {
        $notes = '';
        $description = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_notess.php');
            }
            $notes = $item->notes;
            $description = $item->description;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('category_hs_codes/form.php', [
            'id' => $id,
            'notes' => $notes,
            'description' => $description,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'notess',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
