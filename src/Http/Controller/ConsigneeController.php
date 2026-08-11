<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\ConsigneeService;
use App\Exception\ValidationException;

class ConsigneeController extends BaseController
{
    private ConsigneeService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        ConsigneeService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('consignee_names', 'Consignee');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('consignee_names.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_consignee_names' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_consignee_names' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'consignee_name' => $request->post('consignee_name', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Consignee has been updated successfully.');
            return Response::redirect('listing_consignee_names.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("consignee_names.php?id=$id&action=edit_consignee_names");
        } catch (\Throwable $e) {
            $this->logError("ConsigneeController::handleUpdate error: " . $e->getMessage());
            flash_error('The Consignee could not be updated.');
            return Response::redirect("consignee_names.php?id=$id&action=edit_consignee_names");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'consignee_name' => $request->post('consignee_name', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Consignee has been saved successfully.');
            return Response::redirect('listing_consignee_names.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("consignee_names.php");
        } catch (\Throwable $e) {
            $this->logError("ConsigneeController::handleCreate error: " . $e->getMessage());
            flash_error('The Consignee could not be saved.');
            return Response::redirect("consignee_names.php");
        }
    }

    private function showForm(int $id): Response
    {
        $consigneeName = '';
        $description = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_consignee_names.php');
            }
            $consigneeName = $item->consigneeName;
            $description = $item->description;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('consignees/form.php', [
            'id' => $id,
            'consigneeName' => $consigneeName,
            'description' => $description,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'consignee_names',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
