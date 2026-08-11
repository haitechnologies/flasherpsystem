<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\SubcategoryService;
use App\Service\CategoryService;
use App\Exception\ValidationException;

class SubcategoryController extends BaseController
{
    private SubcategoryService $subService;
    private CategoryService $catService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        SubcategoryService $subService,
        CategoryService $catService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->subService = $subService;
        $this->catService = $catService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('subcategories', 'Subcategory');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('subcategories.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_subcategories' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_subcategories' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->subService->update($id, [
                'category_id' => (int)$request->post('category_id', 0),
                'name' => $request->post('name', ''),
                'slug' => $request->post('slug', ''),
                'description' => $request->post('description', ''),
                'icon' => $request->post('icon', ''),
                'meta_title' => $request->post('meta_title', ''),
                'meta_description' => $request->post('meta_description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            return Response::redirect('subcategories.php?id=' . $id . '&action=edit_subcategories&updated=1');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("subcategories.php?id=$id&action=edit_subcategories");
        } catch (\Throwable $e) {
            $this->logError("SubcategoryController::handleUpdate error: " . $e->getMessage());
            flash_error('The Subcategory could not be updated.');
            return Response::redirect("subcategories.php?id=$id&action=edit_subcategories");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $newId = $this->subService->create([
                'category_id' => (int)$request->post('category_id', 0),
                'name' => $request->post('name', ''),
                'slug' => $request->post('slug', ''),
                'description' => $request->post('description', ''),
                'icon' => $request->post('icon', ''),
                'meta_title' => $request->post('meta_title', ''),
                'meta_description' => $request->post('meta_description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            return Response::redirect('subcategories.php?id=' . $newId . '&action=edit_subcategories&created=1');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("subcategories.php");
        } catch (\Throwable $e) {
            $this->logError("SubcategoryController::handleCreate error: " . $e->getMessage());
            flash_error('The Subcategory could not be saved.');
            return Response::redirect("subcategories.php");
        }
    }

    private function showForm(int $id): Response
    {
        $categoryId = 0;
        $name = '';
        $slug = '';
        $description = '';
        $icon = '';
        $metaTitle = '';
        $metaDescription = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->subService->getById($id);
            if ($item === null) {
                flash_error('Subcategory not found.');
                return Response::redirect('listing_subcategories.php');
            }
            $categoryId = $item->categoryId;
            $name = $item->name;
            $slug = $item->slug;
            $description = $item->description;
            $icon = $item->icon;
            $metaTitle = $item->metaTitle;
            $metaDescription = $item->metaDescription;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('subcategories/form.php', [
            'id' => $id,
            'categoryId' => $categoryId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'icon' => $icon,
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'subcategories',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
            'allCategories' => $this->catService->list(),
        ]));
    }
}
