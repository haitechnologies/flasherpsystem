<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\CurrencyService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class CurrencyController extends BaseController
{
    private CurrencyService $currencyService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        CurrencyService $currencyService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->currencyService = $currencyService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('currencies', 'Currency');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_currencies.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_currencies' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_currencies' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $data = [
            'currency' => $request->getString('currency'),
            'publish' => $request->get('publish') ? 1 : 0,
        ];

        try {
            $this->currencyService->update($id, $data, $this->orgId);
            flash_success('The Currency has been updated successfully.');
            return Response::redirect('listing_currencies.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("currencies.php?id=$id&action=edit_currencies");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("currencies.php?id=$id&action=edit_currencies");
        } catch (\Throwable $e) {
            $this->logError("CurrencyController::handleUpdate error: " . $e->getMessage());
            flash_error('The Currency could not be updated.');
            return Response::redirect("currencies.php?id=$id&action=edit_currencies");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $data = [
            'currency' => $request->getString('currency'),
            'publish' => $request->get('publish') ? 1 : 0,
        ];

        try {
            $this->currencyService->create($data, $this->orgId, $this->userId);
            flash_success('The Currency has been saved successfully.');
            return Response::redirect('listing_currencies.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("currencies.php");
        } catch (\Throwable $e) {
            $this->logError("CurrencyController::handleCreate error: " . $e->getMessage());
            flash_error('The Currency could not be saved.');
            return Response::redirect("currencies.php");
        }
    }

    private function showForm(int $id): Response
    {
        $currencyName = '';
        $publish = 1;
        $error_message = '';
        $moduleCaption = $this->moduleCaption;
        $module = 'currencies';
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;

        if ($id > 0) {
            try {
                $currency = $this->currencyService->getById($id, $this->orgId);
                $currencyName = $currency->currency;
                $publish = $currency->isActive ? 1 : 0;
            } catch (NotFoundException $e) {
                $error_message = $e->getMessage();
            }
        }

        return Response::html($this->view->render('currencies/form.php', [
            'id' => $id,
            'currencyName' => $currencyName,
            'publish' => $publish,
            'error_message' => $error_message,
            'moduleCaption' => $moduleCaption,
            'module' => $module,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
