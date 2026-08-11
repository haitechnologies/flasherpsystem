<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\TaxTreatmentService;
use App\Exception\ValidationException;

class TaxTreatmentController extends BaseController
{
    private TaxTreatmentService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        TaxTreatmentService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('tax_treatments', 'Tax Treatment');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('tax_treatments.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_tax_treatments' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_tax_treatments' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'tax_treatment' => $request->post('tax_treatment', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Tax Treatment has been updated successfully.');
            return Response::redirect('listing_tax_treatments.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("tax_treatments.php?id=$id&action=edit_tax_treatments");
        } catch (\Throwable $e) {
            $this->logError("TaxTreatmentController::handleUpdate error: " . $e->getMessage());
            flash_error('The Tax Treatment could not be updated.');
            return Response::redirect("tax_treatments.php?id=$id&action=edit_tax_treatments");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'tax_treatment' => $request->post('tax_treatment', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Tax Treatment has been saved successfully.');
            return Response::redirect('listing_tax_treatments.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("tax_treatments.php");
        } catch (\Throwable $e) {
            $this->logError("TaxTreatmentController::handleCreate error: " . $e->getMessage());
            flash_error('The Tax Treatment could not be saved.');
            return Response::redirect("tax_treatments.php");
        }
    }

    private function showForm(int $id): Response
    {
        $taxTreatment = '';
        $description = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_tax_treatments.php');
            }
            $taxTreatment = $item->taxTreatment;
            $description = $item->description;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('tax_treatments/form.php', [
            'id' => $id,
            'taxTreatment' => $taxTreatment,
            'description' => $description,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'tax_treatments',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
