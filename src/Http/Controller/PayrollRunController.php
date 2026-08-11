<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\PayrollRunService;
use App\Exception\ValidationException;

class PayrollRunController extends BaseController
{
    private PayrollRunService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        PayrollRunService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('payroll_runs', 'Payroll Run');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_period_starts.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_period_starts' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_period_starts' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'period_start' => $request->post('period_start', ''),
                'description' => $request->post('description', ''),
            ], $this->userId);
            flash_success('The Payroll Run has been updated successfully.');
            return Response::redirect('listing_period_starts.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("listing_period_starts.php");
        } catch (\Throwable $e) {
            $this->logError("PayrollRunController::handleUpdate error: " . $e->getMessage());
            flash_error('The Payroll Run could not be updated.');
            return Response::redirect("listing_period_starts.php");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'period_start' => $request->post('period_start', ''),
                'description' => $request->post('description', ''),
            ], $this->userId);
            flash_success('The Payroll Run has been saved successfully.');
            return Response::redirect('listing_period_starts.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("listing_period_starts.php");
        } catch (\Throwable $e) {
            $this->logError("PayrollRunController::handleCreate error: " . $e->getMessage());
            flash_error('The Payroll Run could not be saved.');
            return Response::redirect("listing_period_starts.php");
        }
    }

    private function showForm(int $id): Response
    {
        $periodStart = '';
        $description = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_payroll_runs.php');
            }
            $periodStart = $item->periodStart;
            $description = $item->description;
        }

        return Response::html($this->view->render('period_starts/form.php', [
            'id' => $id,
            'periodStart' => $periodStart,
            'description' => $description,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'payroll_runs',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
