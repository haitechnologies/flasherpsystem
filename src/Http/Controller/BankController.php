<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Container;
use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\BankService;
use App\Service\CurrencyService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class BankController extends BaseController
{
    private BankService $bankService;
    private CurrencyService $currencyService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        BankService $bankService,
        CurrencyService $currencyService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->bankService = $bankService;
        $this->currencyService = $currencyService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('banks', 'Bank Account');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_banks.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_banks' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_banks' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $data = [
            'account_name' => $request->getString('account_name'),
            'account_code' => $request->getString('account_code'),
            'opening_balance' => $request->getString('opening_balance'),
            'account_id' => $request->getString('account_id'),
            'currency' => $request->getString('currency'),
            'bank_name' => $request->getString('bank_name'),
            'branch' => $request->getString('branch'),
            'address' => $request->getString('address'),
            'iban' => $request->getString('iban'),
            'routing_number' => $request->getString('routing_number'),
            'description' => $request->getString('description'),
            'is_primary' => $request->get('is_primary') ? 1 : 0,
            'publish' => $request->get('publish') ? 1 : 0,
        ];

        try {
            $this->bankService->update($id, $data, $this->orgId, $this->userId);
            flash_success('The Bank Account has been updated successfully.');
            return Response::redirect('listing_banks.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("banks.php?id=$id&action=edit_banks");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("banks.php?id=$id&action=edit_banks");
        } catch (\Throwable $e) {
            flash_error('The Bank Account could not be updated.');
            return Response::redirect("banks.php?id=$id&action=edit_banks");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $data = [
            'account_name' => $request->getString('account_name'),
            'account_code' => $request->getString('account_code'),
            'opening_balance' => $request->getString('opening_balance'),
            'account_id' => $request->getString('account_id'),
            'currency' => $request->getString('currency'),
            'bank_name' => $request->getString('bank_name'),
            'branch' => $request->getString('branch'),
            'address' => $request->getString('address'),
            'iban' => $request->getString('iban'),
            'routing_number' => $request->getString('routing_number'),
            'description' => $request->getString('description'),
            'is_primary' => $request->get('is_primary') ? 1 : 0,
            'publish' => $request->get('publish') ? 1 : 0,
        ];

        try {
            $this->bankService->create($data, $this->orgId, $this->userId);
            flash_success('The Bank Account has been saved successfully.');
            return Response::redirect('listing_banks.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("banks.php");
        } catch (\Throwable $e) {
            flash_error('The Bank Account could not be saved.');
            return Response::redirect("banks.php");
        }
    }

    private function showForm(int $id): Response
    {
        $accountName = '';
        $accountCode = '';
        $openingBalance = '0.00';
        $accountId = '';
        $currency = '0';
        $bankName = '';
        $branch = '';
        $address = '';
        $iban = '';
        $routingNumber = '';
        $description = '';
        $isPrimary = 0;
        $publish = 1;
        $error_message = '';
        $moduleCaption = $this->moduleCaption;
        $module = 'banks';
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;

        if ($id > 0) {
            try {
                $bank = $this->bankService->getById($id, $this->orgId);
                $accountName = $bank->accountName;
                $accountCode = $bank->accountCode;
                $openingBalance = (string)$bank->openingBalance;
                $accountId = $bank->accountId !== null ? (string)$bank->accountId : '';
                $currency = (string)$bank->currency;
                $bankName = $bank->bankName;
                $branch = $bank->branch;
                $address = $bank->address;
                $iban = $bank->iban;
                $routingNumber = $bank->routingNumber;
                $description = $bank->description;
                $isPrimary = $bank->isPrimary ? 1 : 0;
                $publish = $bank->isActive ? 1 : 0;
            } catch (NotFoundException $e) {
                $error_message = $e->getMessage();
            }
        }

        $allCurrencies = [];
        try {
            $allCurrencies = $this->db->fetchAll(
                "SELECT id, currency FROM `" . DB::CURRENCIES . "` WHERE is_active=1 ORDER BY currency"
            );
        } catch (\Throwable $e) {
            $allCurrencies = [];
        }

        $allAccounts = [];
        try {
            $allAccounts = $this->db->fetchAll(
                "SELECT id, account_name, account_code FROM `" . DB::ACCOUNTS . "` WHERE is_active=1 AND account_type IN ('Assets') ORDER BY account_name"
            );
        } catch (\Throwable $e) {
            $allAccounts = [];
        }

        return Response::html($this->view->render('banks/form.php', [
            'id' => $id,
            'accountName' => $accountName,
            'accountCode' => $accountCode,
            'openingBalance' => $openingBalance,
            'accountId' => $accountId,
            'currency' => $currency,
            'bankName' => $bankName,
            'branch' => $branch,
            'address' => $address,
            'iban' => $iban,
            'routingNumber' => $routingNumber,
            'description' => $description,
            'isPrimary' => $isPrimary,
            'publish' => $publish,
            'error_message' => $error_message,
            'moduleCaption' => $moduleCaption,
            'module' => $module,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'allCurrencies' => $allCurrencies,
            'allAccounts' => $allAccounts,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
