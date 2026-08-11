<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\ShipperService;
use App\Exception\ValidationException;

class ShipperController extends BaseController
{
    private ShipperService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        ShipperService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('shipper_names', 'Shipper');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('shipper_names.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_shipper_names' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_shipper_names' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'shipper_name' => $request->post('shipper_name', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Shipper has been updated successfully.');
            return Response::redirect('listing_shipper_names.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("shipper_names.php?id=$id&action=edit_shipper_names");
        } catch (\Throwable $e) {
            $this->logError("ShipperController::handleUpdate error: " . $e->getMessage());
            flash_error('The Shipper could not be updated.');
            return Response::redirect("shipper_names.php?id=$id&action=edit_shipper_names");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'shipper_name' => $request->post('shipper_name', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Shipper has been saved successfully.');
            return Response::redirect('listing_shipper_names.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("shipper_names.php");
        } catch (\Throwable $e) {
            $this->logError("ShipperController::handleCreate error: " . $e->getMessage());
            flash_error('The Shipper could not be saved.');
            return Response::redirect("shipper_names.php");
        }
    }

    private function showForm(int $id): Response
    {
        $shipperName = '';
        $description = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_shipper_names.php');
            }
            $shipperName = $item->shipperName;
            $description = $item->description;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('shippers/form.php', [
            'id' => $id,
            'shipperName' => $shipperName,
            'description' => $description,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'shipper_names',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
