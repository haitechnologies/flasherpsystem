<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\PaymentMethodService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class PaymentMethodController extends BaseController
{
    private PaymentMethodService $paymentMethodService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        PaymentMethodService $paymentMethodService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->paymentMethodService = $paymentMethodService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('payment_methods', 'Payment method');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_payment_methods.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_payment_methods' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_payment_methods' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $data = [
            'payment_method' => $request->getString('payment_method'),
            'is_active' => $request->get('is_active') ? 1 : 0,
        ];

        try {
            $this->paymentMethodService->update($id, $data, $this->orgId);
            flash_success('The Payment method has been updated successfully.');
            return Response::redirect('listing_payment_methods.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("payment_methods.php?id=$id&action=edit_payment_methods");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("payment_methods.php?id=$id&action=edit_payment_methods");
        } catch (\Throwable $e) {
            $this->logError("PaymentMethodController::handleUpdate error: " . $e->getMessage());
            flash_error('The Payment method could not be updated.');
            return Response::redirect("payment_methods.php?id=$id&action=edit_payment_methods");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $data = [
            'payment_method' => $request->getString('payment_method'),
            'is_active' => $request->get('is_active') ? 1 : 0,
        ];

        try {
            $this->paymentMethodService->create($data, $this->orgId, $this->userId);
            flash_success('The Payment method has been saved successfully.');
            return Response::redirect('listing_payment_methods.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("payment_methods.php");
        } catch (\Throwable $e) {
            $this->logError("PaymentMethodController::handleCreate error: " . $e->getMessage());
            flash_error('The Payment method could not be saved.');
            return Response::redirect("payment_methods.php");
        }
    }

    private function showForm(int $id): Response
    {
        $paymentMethodName = '';
        $isActive = 1;
        $error_message = '';
        $moduleCaption = $this->moduleCaption;
        $module = 'payment_methods';
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;

        if ($id > 0) {
            try {
                $method = $this->paymentMethodService->getById($id, $this->orgId);
                $paymentMethodName = $method->paymentMethod;
                $isActive = $method->isActive ? 1 : 0;
            } catch (NotFoundException $e) {
                $error_message = $e->getMessage();
            }
        }

        return Response::html($this->view->render('payment_methods/form.php', [
            'id' => $id,
            'paymentMethodName' => $paymentMethodName,
            'isActive' => $isActive,
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
