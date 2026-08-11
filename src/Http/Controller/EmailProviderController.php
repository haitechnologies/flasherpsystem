<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\EmailProviderService;
use App\Exception\ValidationException;

class EmailProviderController extends BaseController
{
    private EmailProviderService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        EmailProviderService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('email_providers', 'Email Provider');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('email_providers.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_email_providers' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_email_providers' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'provider_name' => $request->post('provider_name', ''),
                'email_encryption' => $request->post('email_encryption', 'NONE'),
                'smtp_host' => $request->post('smtp_host', ''),
                'smtp_port' => $request->post('smtp_port', ''),
                'email' => $request->post('email', ''),
                'smtp_username' => $request->post('smtp_username', ''),
                'smtp_password' => $request->post('smtp_password', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
                'is_primary' => $request->has('is_primary') ? 1 : 0,
            ], $this->userId);
            flash_success('Email Provider updated successfully.');
            return Response::redirect('listing_email_providers.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("email_providers.php?id=$id&action=edit_email_providers");
        } catch (\Throwable $e) {
            $this->logError("EmailProviderController::handleUpdate error: " . $e->getMessage());
            flash_error('Email Provider could not be updated.');
            return Response::redirect("email_providers.php?id=$id&action=edit_email_providers");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'provider_name' => $request->post('provider_name', ''),
                'email_encryption' => $request->post('email_encryption', 'NONE'),
                'smtp_host' => $request->post('smtp_host', ''),
                'smtp_port' => $request->post('smtp_port', ''),
                'email' => $request->post('email', ''),
                'smtp_username' => $request->post('smtp_username', ''),
                'smtp_password' => $request->post('smtp_password', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
                'is_primary' => $request->has('is_primary') ? 1 : 0,
            ], $this->userId);
            flash_success('Email Provider saved successfully.');
            return Response::redirect('listing_email_providers.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("email_providers.php");
        } catch (\Throwable $e) {
            $this->logError("EmailProviderController::handleCreate error: " . $e->getMessage());
            flash_error('Email Provider could not be saved.');
            return Response::redirect("email_providers.php");
        }
    }

    private function showForm(int $id): Response
    {
        $providerName = '';
        $emailEncryption = 'NONE';
        $smtpHost = '';
        $smtpPort = '';
        $email = '';
        $smtpUsername = '';
        $smtpPassword = '';
        $publish = 1;
        $isPrimary = 0;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_email_providers.php');
            }
            $providerName = $item->providerName;
            $emailEncryption = $item->emailEncryption;
            $smtpHost = $item->smtpHost;
            $smtpPort = $item->smtpPort;
            $email = $item->email;
            $smtpUsername = $item->smtpUsername;
            $smtpPassword = $item->smtpPassword;
            $publish = $item->isActive ? 1 : 0;
            $isPrimary = $item->isPrimary ? 1 : 0;
        }

        return Response::html($this->view->render('email_providers/form.php', [
            'id' => $id,
            'providerName' => $providerName,
            'emailEncryption' => $emailEncryption,
            'smtpHost' => $smtpHost,
            'smtpPort' => $smtpPort,
            'email' => $email,
            'smtpUsername' => $smtpUsername,
            'smtpPassword' => $smtpPassword,
            'publish' => $publish,
            'isPrimary' => $isPrimary,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'email_providers',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
