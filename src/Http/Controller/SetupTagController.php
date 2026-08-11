<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\SetupTagService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class SetupTagController extends BaseController
{
    private SetupTagService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        SetupTagService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('setup_tags', 'Tag');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_setup_tags.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_setup_tags' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_setup_tags' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $data = [
            'tag_name' => $request->getString('tag_name'),
            'tag_type' => $request->getString('tag_type'),
            'publish' => $request->get('publish') ? 1 : 0,
        ];

        try {
            $this->service->update($id, $data, $this->userId);
            flash_success('The Tag has been updated successfully.');
            return Response::redirect('listing_setup_tags.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("setup_tags.php?id=$id&action=edit_setup_tags");
        } catch (\Throwable $e) {
            $this->logError("SetupTagController::handleUpdate error: " . $e->getMessage());
            flash_error('The Tag could not be updated.');
            return Response::redirect("setup_tags.php?id=$id&action=edit_setup_tags");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $data = [
            'tag_name' => $request->getString('tag_name'),
            'tag_type' => $request->getString('tag_type'),
            'publish' => $request->get('publish') ? 1 : 0,
        ];

        try {
            $this->service->create($data, $this->userId);
            flash_success('The Tag has been saved successfully.');
            return Response::redirect('listing_setup_tags.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("setup_tags.php");
        } catch (\Throwable $e) {
            $this->logError("SetupTagController::handleCreate error: " . $e->getMessage());
            flash_error('The Tag could not be saved.');
            return Response::redirect("setup_tags.php");
        }
    }

    private function showForm(int $id): Response
    {
        $tagName = '';
        $tagType = '';
        $publish = 1;
        $error_message = '';
        $moduleCaption = $this->moduleCaption;
        $module = 'setup_tags';
        $session_user_id = $this->userId;

        if ($id > 0) {
            $model = $this->service->getById($id);
            if ($model !== null) {
                $tagName = $model->tagName;
                $tagType = $model->tagType;
                $publish = $model->isActive ? 1 : 0;
            }
        }

        return Response::html($this->view->render('setup_tags/form.php', [
            'id' => $id,
            'tagName' => $tagName,
            'tagType' => $tagType,
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
