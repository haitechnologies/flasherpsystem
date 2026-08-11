<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\HscodeService;
use App\Exception\ValidationException;

class HscodeController extends BaseController
{
    private HscodeService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        HscodeService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('hscodes', 'HS Code');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('hscodes.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_hscodes' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_hscodes' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'code' => $request->post('code', ''),
                'old_code' => $request->post('old_code', ''),
                'level' => (int)$request->post('level', 0),
                'duty_rate' => $request->post('duty_rate', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('HS Code updated successfully.');
            return Response::redirect('listing_hscodes.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("hscodes.php?id=$id&action=edit_hscodes");
        } catch (\Throwable $e) {
            $this->logError("HscodeController::handleUpdate error: " . $e->getMessage());
            flash_error('HS Code could not be updated.');
            return Response::redirect("hscodes.php?id=$id&action=edit_hscodes");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'code' => $request->post('code', ''),
                'old_code' => $request->post('old_code', ''),
                'level' => (int)$request->post('level', 0),
                'duty_rate' => $request->post('duty_rate', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('HS Code saved successfully.');
            return Response::redirect('listing_hscodes.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("hscodes.php");
        } catch (\Throwable $e) {
            $this->logError("HscodeController::handleCreate error: " . $e->getMessage());
            flash_error('HS Code could not be saved.');
            return Response::redirect("hscodes.php");
        }
    }

    private function showForm(int $id): Response
    {
        $code = '';
        $oldCode = '';
        $level = 0;
        $dutyRate = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_hscodes.php');
            }
            $code = $item->code;
            $oldCode = $item->oldCode;
            $level = $item->level;
            $dutyRate = $item->dutyRate;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('hscodes/form.php', [
            'id' => $id,
            'code' => $code,
            'oldCode' => $oldCode,
            'level' => $level,
            'dutyRate' => $dutyRate,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'hscodes',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
