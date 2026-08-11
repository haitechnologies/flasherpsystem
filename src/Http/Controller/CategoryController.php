<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\CategoryService;
use App\Exception\ValidationException;

class CategoryController extends BaseController
{
    private CategoryService $categoryService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        CategoryService $categoryService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->categoryService = $categoryService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('categories', 'Category');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('categories.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_categories' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_categories' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->categoryService->update($id, [
                'name' => $request->post('name', ''),
                'slug' => $request->post('slug', ''),
                'description' => $request->post('description', ''),
                'icon' => $request->post('icon', ''),
                'meta_title' => $request->post('meta_title', ''),
                'meta_description' => $request->post('meta_description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            return Response::redirect('categories.php?id=' . $id . '&action=edit_categories&updated=1');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("categories.php?id=$id&action=edit_categories");
        } catch (\Throwable $e) {
            $this->logError("CategoryController::handleUpdate error: " . $e->getMessage());
            flash_error('The Category could not be updated.');
            return Response::redirect("categories.php?id=$id&action=edit_categories");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $newId = $this->categoryService->create([
                'name' => $request->post('name', ''),
                'slug' => $request->post('slug', ''),
                'description' => $request->post('description', ''),
                'icon' => $request->post('icon', ''),
                'meta_title' => $request->post('meta_title', ''),
                'meta_description' => $request->post('meta_description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            return Response::redirect('categories.php?id=' . $newId . '&action=edit_categories&created=1');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("categories.php");
        } catch (\Throwable $e) {
            $this->logError("CategoryController::handleCreate error: " . $e->getMessage());
            flash_error('The Category could not be saved.');
            return Response::redirect("categories.php");
        }
    }

    private function showForm(int $id): Response
    {
        $name = '';
        $slug = '';
        $description = '';
        $icon = '';
        $metaTitle = '';
        $metaDescription = '';
        $publish = 1;

        if ($id > 0) {
            $category = $this->categoryService->getById($id);
            if ($category === null) {
                flash_error('Category not found.');
                return Response::redirect('listing_categories.php');
            }
            $name = $category->name;
            $slug = $category->slug;
            $description = $category->description;
            $icon = $category->icon;
            $metaTitle = $category->metaTitle;
            $metaDescription = $category->metaDescription;
            $publish = $category->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('categories/form.php', [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'icon' => $icon,
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'categories',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
