<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\LeadAttachmentService;
use App\Security\Roles;
use App\Exception\ValidationException;

class LeadAttachmentController extends BaseController
{
    private LeadAttachmentService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        LeadAttachmentService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('lead_attachments', 'Lead Attachment');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('attachment_id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_lead_attachments' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_lead_attachments' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($request, $id),
        };
    }

    private function handleCreate(Request $request): Response
    {
        $file = $request->getFile('document');
        $leadId = $request->getInt('lead_id');

        try {
            $this->service->createAttachment([
                'lead_id' => $leadId,
                'description' => $request->post('attachment_name', ''),
            ], $file, $this->orgId, $this->userId);

            flash_success('The Lead Attachment has been saved successfully.');
            return Response::redirect("lead_attachments.php?lead_id=$leadId");
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("lead_attachments.php?lead_id=$leadId");
        } catch (\Throwable $e) {
            $this->logError("LeadAttachmentController::handleCreate error: " . $e->getMessage());
            flash_error($e->getMessage());
            return Response::redirect("lead_attachments.php?lead_id=$leadId");
        }
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $file = $request->getFile('document');
        $leadId = $request->getInt('lead_id');

        try {
            $this->service->updateAttachment($id, [
                'description' => $request->post('attachment_name', ''),
            ], $file, $this->orgId, $this->userId);

            flash_success('The Lead Attachment has been updated successfully.');
            return Response::redirect("lead_attachments.php?lead_id=$leadId");
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("lead_attachments.php?lead_id=$leadId&attachment_id=$id&action=edit_lead_attachments");
        } catch (\Throwable $e) {
            $this->logError("LeadAttachmentController::handleUpdate error: " . $e->getMessage());
            flash_error($e->getMessage());
            return Response::redirect("lead_attachments.php?lead_id=$leadId&attachment_id=$id&action=edit_lead_attachments");
        }
    }

    private function showForm(Request $request, int $id): Response
    {
        $action = $request->getString('action');
        $leadId = $request->getInt('lead_id');
        $error_message = $request->getString('error_message');
        if (empty($error_message)) {
            foreach (\App\Core\FlashMessage::all() as $fm) {
                if ($fm['type'] === 'danger') { $error_message = $fm['message']; break; }
            }
        }

        $attachmentName = '';
        $attachmentFilename = '';

        $existingAttachments = [];
        $existingCount = 0;

        if ($leadId > 0) {
            try {
                $attachments = $this->service->getAttachmentsByLead($leadId, $this->orgId);
                $existingAttachments = $attachments;
                $existingCount = count($attachments);
            } catch (\Throwable $e) {
                $this->logError("LeadAttachmentController::showForm error: " . $e->getMessage());
            }
        }

        if ($id > 0 && $leadId > 0) {
            try {
                $attachment = $this->service->getAttachment($id, $this->orgId);
                $attachmentName = (string)$attachment->description;
                $attachmentFilename = (string)$attachment->filename;
            } catch (\Throwable $e) {
                $this->logError("LeadAttachmentController::showForm error: " . $e->getMessage());
                $error_message = $e->getMessage();
            }
        }

        $uploadPath = '../uploads/lead_attachments/';

        return Response::html($this->view->render('lead_attachments/form.php', [
            'id' => $id,
            'module' => 'lead_attachments',
            'moduleCaption' => $this->moduleCaption,
            'moduleId' => $this->moduleId,
            'session_user_id' => $this->userId,
            'session_role_id' => $this->roleId,
            'error_message' => $error_message,
            'action' => $action,
            'lead_id' => $leadId,
            'attachment_name' => $attachmentName,
            'attachment_filename' => $attachmentFilename,
            'existingAttachments' => $existingAttachments,
            'existingCount' => $existingCount,
            'uploadPath' => $uploadPath,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
            'canDelete' => $this->canDelete(),
        ]));
    }
}
