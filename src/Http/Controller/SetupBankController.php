<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\SetupBankService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class SetupBankController extends BaseController
{
    private SetupBankService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        SetupBankService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('setup_banks', 'Banks (Institutions)');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_setup_banks.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_setup_banks' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_setup_banks' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $data = [
            'institution_name' => $request->getString('institution_name'),
            'head_office' => $request->getString('head_office'),
        ];

        try {
            $this->service->update($id, $data, $this->orgId, $this->userId);
            flash_success('The Bank Institution has been updated successfully.');
            return Response::redirect('listing_setup_banks.php');
        } catch (ValidationException $e) {
            flash_error(current($e->getErrors()));
            return Response::redirect("setup_banks.php?id=$id&action=edit_setup_banks");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("setup_banks.php?id=$id&action=edit_setup_banks");
        } catch (\Throwable $e) {
            $this->logError("SetupBankController::handleUpdate error: " . $e->getMessage());
            flash_error('The Bank Institution could not be updated.');
            return Response::redirect("setup_banks.php?id=$id&action=edit_setup_banks");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $data = [
            'institution_name' => $request->getString('institution_name'),
            'head_office' => $request->getString('head_office'),
        ];

        try {
            $this->service->create($data, $this->orgId, $this->userId);
            flash_success('The Bank Institution has been saved successfully.');
            return Response::redirect('listing_setup_banks.php');
        } catch (ValidationException $e) {
            flash_error(current($e->getErrors()));
            return Response::redirect('setup_banks.php');
        } catch (\Throwable $e) {
            $this->logError("SetupBankController::handleCreate error: " . $e->getMessage());
            flash_error('The Bank Institution could not be saved.');
            return Response::redirect('setup_banks.php');
        }
    }

    private function showForm(int $id): Response
    {
        $institutionName = '';
        $headOffice = '';
        $error_message = '';
        $moduleCaption = $this->moduleCaption;
        $module = 'setup_banks';
        $session_user_id = $this->userId;

        if ($id > 0) {
            $model = $this->service->getById($id, $this->orgId);
            if ($model !== null) {
                $institutionName = $model->institutionName;
                $headOffice = $model->headOffice;
            }
        }

        return Response::html($this->view->render('setup_banks/form.php', [
            'id' => $id,
            'institutionName' => $institutionName,
            'headOffice' => $headOffice,
            'error_message' => $error_message,
            'moduleCaption' => $moduleCaption,
            'module' => $module,
            'session_user_id' => $session_user_id,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
