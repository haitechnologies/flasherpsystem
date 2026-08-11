<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\DocumentCategoryService;
use App\Exception\ValidationException;
use App\Security\Roles;

class DocumentCategoryController extends BaseController
{
    private DocumentCategoryService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        DocumentCategoryService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('document_categories', 'Document Category');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('document_categories.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_document_categories' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_document_categories' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'document_category' => $request->post('document_category', ''),
                'document_category_type' => $request->post('document_category_type', 'employees'),
            ], $this->userId);
            flash_success('The Document Category has been updated successfully.');
            return Response::redirect('listing_document_categories.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("document_categories.php?id=$id&action=edit_document_categories");
        } catch (\Throwable $e) {
            $this->logError("DocumentCategoryController::handleUpdate error: " . $e->getMessage());
            flash_error('The Document Category could not be updated.');
            return Response::redirect("document_categories.php?id=$id&action=edit_document_categories");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'document_category' => $request->post('document_category', ''),
                'document_category_type' => $request->post('document_category_type', 'employees'),
            ], $this->userId);
            flash_success('The Document Category has been saved successfully.');
            return Response::redirect('listing_document_categories.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("document_categories.php");
        } catch (\Throwable $e) {
            $this->logError("DocumentCategoryController::handleCreate error: " . $e->getMessage());
            flash_error('The Document Category could not be saved.');
            return Response::redirect("document_categories.php");
        }
    }

    private function showForm(int $id): Response
    {
        $documentCategory = '';
        $documentCategoryType = 'employees';

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_document_categories.php');
            }
            $documentCategory = $item->documentCategory;
            $documentCategoryType = $item->documentCategoryType;
        }

        $isFullAccess = Roles::hasFullAccess($this->roleId);

        return Response::html($this->view->render('document_categories/form.php', [
            'id' => $id,
            'documentCategory' => $documentCategory,
            'documentCategoryType' => $documentCategoryType,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'document_categories',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
            'isFullAccess' => $isFullAccess,
        ]));
    }
}
