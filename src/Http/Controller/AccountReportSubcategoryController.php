<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\AccountReportSubcategoryService;
use App\Exception\ValidationException;

class AccountReportSubcategoryController extends BaseController
{
    private AccountReportSubcategoryService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        AccountReportSubcategoryService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('report_names', 'Account Report Subcategory');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('report_names.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_report_names' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_report_names' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'report_name' => $request->post('report_name', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Account Report Subcategory has been updated successfully.');
            return Response::redirect('listing_report_names.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("report_names.php?id=$id&action=edit_report_names");
        } catch (\Throwable $e) {
            $this->logError("AccountReportSubcategoryController::handleUpdate error: " . $e->getMessage());
            flash_error('The Account Report Subcategory could not be updated.');
            return Response::redirect("report_names.php?id=$id&action=edit_report_names");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'report_name' => $request->post('report_name', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Account Report Subcategory has been saved successfully.');
            return Response::redirect('listing_report_names.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("report_names.php");
        } catch (\Throwable $e) {
            $this->logError("AccountReportSubcategoryController::handleCreate error: " . $e->getMessage());
            flash_error('The Account Report Subcategory could not be saved.');
            return Response::redirect("report_names.php");
        }
    }

    private function showForm(int $id): Response
    {
        $reportName = '';
        $description = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_report_names.php');
            }
            $reportName = $item->reportName;
            $description = $item->description;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('accounts_report_subcategories/form.php', [
            'id' => $id,
            'reportName' => $reportName,
            'description' => $description,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'report_names',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
