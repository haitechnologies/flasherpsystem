<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\PayrollComponentService;
use App\Exception\ValidationException;

class PayrollComponentController extends BaseController
{
    private PayrollComponentService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        PayrollComponentService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('payroll_components', 'Payroll Component');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_component_names.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_component_names' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_component_names' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'component_name' => $request->post('component_name', ''),
                'description' => $request->post('description', ''),
            ], $this->userId);
            flash_success('The Payroll Component has been updated successfully.');
            return Response::redirect('listing_component_names.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("listing_component_names.php");
        } catch (\Throwable $e) {
            $this->logError("PayrollComponentController::handleUpdate error: " . $e->getMessage());
            flash_error('The Payroll Component could not be updated.');
            return Response::redirect("listing_component_names.php");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'component_name' => $request->post('component_name', ''),
                'description' => $request->post('description', ''),
            ], $this->userId);
            flash_success('The Payroll Component has been saved successfully.');
            return Response::redirect('listing_component_names.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("listing_component_names.php");
        } catch (\Throwable $e) {
            $this->logError("PayrollComponentController::handleCreate error: " . $e->getMessage());
            flash_error('The Payroll Component could not be saved.');
            return Response::redirect("listing_component_names.php");
        }
    }

    private function showForm(int $id): Response
    {
        $componentName = '';
        $description = '';

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_payroll_components.php');
            }
            $componentName = $item->componentName;
            $description = $item->description;
        }

        return Response::html($this->view->render('component_names/form.php', [
            'id' => $id,
            'componentName' => $componentName,
            'description' => $description,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'payroll_components',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
