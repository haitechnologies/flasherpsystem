<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\CarrierService;
use App\Exception\ValidationException;

class CarrierController extends BaseController
{
    private CarrierService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        CarrierService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('carrier_names', 'Carrier');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('carrier_names.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_carrier_names' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_carrier_names' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'carrier_name' => $request->post('carrier_name', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Carrier has been updated successfully.');
            return Response::redirect('listing_carrier_names.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("carrier_names.php?id=$id&action=edit_carrier_names");
        } catch (\Throwable $e) {
            $this->logError("CarrierController::handleUpdate error: " . $e->getMessage());
            flash_error('The Carrier could not be updated.');
            return Response::redirect("carrier_names.php?id=$id&action=edit_carrier_names");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'carrier_name' => $request->post('carrier_name', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Carrier has been saved successfully.');
            return Response::redirect('listing_carrier_names.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("carrier_names.php");
        } catch (\Throwable $e) {
            $this->logError("CarrierController::handleCreate error: " . $e->getMessage());
            flash_error('The Carrier could not be saved.');
            return Response::redirect("carrier_names.php");
        }
    }

    private function showForm(int $id): Response
    {
        $carrierName = '';
        $description = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_carrier_names.php');
            }
            $carrierName = $item->carrierName;
            $description = $item->description;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('carriers/form.php', [
            'id' => $id,
            'carrierName' => $carrierName,
            'description' => $description,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'carrier_names',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
