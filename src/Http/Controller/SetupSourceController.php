<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\SetupSourceService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class SetupSourceController extends BaseController
{
    private SetupSourceService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        SetupSourceService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('setup_sources', 'Source');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_setup_sources.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_setup_sources' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_setup_sources' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $data = [
            'source_name' => $request->getString('source_name'),
            'source_type' => $request->getString('source_type'),
            'publish' => $request->get('publish') ? 1 : 0,
        ];

        try {
            $this->service->update($id, $data, $this->userId);
            flash_success('The Source has been updated successfully.');
            return Response::redirect('listing_setup_sources.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("setup_sources.php?id=$id&action=edit_setup_sources");
        } catch (\Throwable $e) {
            $this->logError("SetupSourceController::handleUpdate error: " . $e->getMessage());
            flash_error('The Source could not be updated.');
            return Response::redirect("setup_sources.php?id=$id&action=edit_setup_sources");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $data = [
            'source_name' => $request->getString('source_name'),
            'source_type' => $request->getString('source_type'),
            'publish' => $request->get('publish') ? 1 : 0,
        ];

        try {
            $this->service->create($data, $this->userId);
            flash_success('The Source has been saved successfully.');
            return Response::redirect('listing_setup_sources.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("setup_sources.php");
        } catch (\Throwable $e) {
            $this->logError("SetupSourceController::handleCreate error: " . $e->getMessage());
            flash_error('The Source could not be saved.');
            return Response::redirect("setup_sources.php");
        }
    }

    private function showForm(int $id): Response
    {
        $sourceName = '';
        $sourceType = '';
        $publish = 1;
        $error_message = '';
        $moduleCaption = $this->moduleCaption;
        $module = 'setup_sources';
        $session_user_id = $this->userId;

        if ($id > 0) {
            $model = $this->service->getById($id);
            if ($model !== null) {
                $sourceName = $model->sourceName;
                $sourceType = $model->sourceType;
                $publish = $model->isActive ? 1 : 0;
            }
        }

        return Response::html($this->view->render('setup_sources/form.php', [
            'id' => $id,
            'sourceName' => $sourceName,
            'sourceType' => $sourceType,
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
