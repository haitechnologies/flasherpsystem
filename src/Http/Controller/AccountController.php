<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\AccountService;
use App\Exception\ValidationException;

class AccountController extends BaseController
{
    private AccountService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        AccountService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('accounts', 'Account');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('accounts.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_accounts' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_accounts' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'parent_id' => (int)$request->post('parent_id', 0),
                'account_type' => $request->post('account_type', ''),
                'account_name' => $request->post('account_name', ''),
                'account_code' => $request->post('account_code', ''),
                'description' => $request->post('description', ''),
                'is_active' => 1,
            ], $this->userId);
            flash_success('Account updated successfully.');
            return Response::redirect('listing_accounts.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("accounts.php?id=$id&action=edit_accounts");
        } catch (\Throwable) {
            flash_error('Account could not be updated.');
            return Response::redirect("accounts.php?id=$id&action=edit_accounts");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'parent_id' => (int)$request->post('parent_id', 0),
                'account_type' => $request->post('account_type', ''),
                'account_name' => $request->post('account_name', ''),
                'account_code' => $request->post('account_code', ''),
                'description' => $request->post('description', ''),
                'is_active' => 1,
            ], $this->userId);
            flash_success('Account saved successfully.');
            return Response::redirect('listing_accounts.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("accounts.php");
        } catch (\Throwable) {
            flash_error('Account could not be saved.');
            return Response::redirect("accounts.php");
        }
    }

    private function showForm(int $id): Response
    {
        $parentId = 0;
        $accountType = '';
        $accountName = '';
        $accountCode = '';
        $description = '';

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_accounts.php');
            }
            $parentId = $item->parentId;
            $accountType = $item->accountType;
            $accountName = $item->accountName;
            $accountCode = $item->accountCode;
            $description = $item->description;
        }

        return Response::html($this->view->render('accounts/form.php', [
            'id' => $id,
            'parentId' => $parentId,
            'accountType' => $accountType,
            'accountName' => $accountName,
            'accountCode' => $accountCode,
            'description' => $description,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'accounts',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
            'parentAccounts' => $this->service->listParents(),
        ]));
    }
}
