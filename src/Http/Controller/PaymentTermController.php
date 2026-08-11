<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\PaymentTermService;
use App\Exception\ValidationException;

class PaymentTermController extends BaseController
{
    private PaymentTermService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        PaymentTermService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('payment_terms', 'Payment Term');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('payment_terms.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_payment_terms' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_payment_terms' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'payment_term' => $request->post('payment_term', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Payment Term has been updated successfully.');
            return Response::redirect('listing_payment_terms.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("payment_terms.php?id=$id&action=edit_payment_terms");
        } catch (\Throwable $e) {
            $this->logError("PaymentTermController::handleUpdate error: " . $e->getMessage());
            flash_error('The Payment Term could not be updated.');
            return Response::redirect("payment_terms.php?id=$id&action=edit_payment_terms");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'payment_term' => $request->post('payment_term', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Payment Term has been saved successfully.');
            return Response::redirect('listing_payment_terms.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("payment_terms.php");
        } catch (\Throwable $e) {
            $this->logError("PaymentTermController::handleCreate error: " . $e->getMessage());
            flash_error('The Payment Term could not be saved.');
            return Response::redirect("payment_terms.php");
        }
    }

    private function showForm(int $id): Response
    {
        $paymentTerm = '';
        $description = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_payment_terms.php');
            }
            $paymentTerm = $item->paymentTerm;
            $description = $item->description;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('payment_terms/form.php', [
            'id' => $id,
            'paymentTerm' => $paymentTerm,
            'description' => $description,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'payment_terms',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
