<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\UserDocumentService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Helper\DateHelper;

class UserDocumentController extends BaseController
{
    private UserDocumentService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        UserDocumentService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('user_documents', 'User Document');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_user_documents' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_user_documents' && $this->canCreate()
                => $this->handleCreate($request),
            $request->isPost() && $action === 'delete_user_documents' && $id > 0 && $this->canDelete()
                => $this->handleDelete($request, $id),
            default => $this->showForm($request, $id),
        };
    }

    private function handleCreate(Request $request): Response
    {
        $file = $request->getFile('document');

        try {
            $this->service->createDocument([
                'user_id' => $request->post('user_id', '0'),
                'document_type' => $request->post('document_type', ''),
                'description' => $request->post('description', ''),
                'issued_date' => $request->post('issued_date', ''),
                'expiry_date' => $request->post('expiry_date', ''),
            ], $file, $this->orgId, $this->userId);

            flash_success('The User Document has been saved successfully.');
            return Response::redirect('listing_user_documents.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("user_documents.php");
        } catch (\Throwable $e) {
            $this->logError("UserDocumentController::handleCreate error: " . $e->getMessage());
            flash_error($e->getMessage());
            return Response::redirect("user_documents.php");
        }
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $file = $request->getFile('document');

        try {
            $this->service->updateDocument($id, [
                'user_id' => $request->post('user_id', '0'),
                'document_type' => $request->post('document_type', ''),
                'description' => $request->post('description', ''),
                'issued_date' => $request->post('issued_date', ''),
                'expiry_date' => $request->post('expiry_date', ''),
            ], $file, $this->orgId, $this->userId);

            flash_success('The User Document has been updated successfully.');
            return Response::redirect('listing_user_documents.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("user_documents.php?id=$id&action=edit_user_documents");
        } catch (\Throwable $e) {
            $this->logError("UserDocumentController::handleUpdate error: " . $e->getMessage());
            flash_error($e->getMessage());
            return Response::redirect("user_documents.php?id=$id&action=edit_user_documents");
        }
    }

    private function handleDelete(Request $request, int $id): Response
    {
        $isSuperAdmin = Roles::hasFullAccess($this->roleId);

        try {
            $this->service->deleteDocument($id, $this->orgId, $this->userId, $isSuperAdmin);
            flash_success('User Document deleted successfully.');
            return Response::redirect('listing_user_documents.php');
        } catch (\Throwable $e) {
            $this->logError("UserDocumentController::handleDelete error: " . $e->getMessage());
            flash_error($e->getMessage());
            return Response::redirect('listing_user_documents.php');
        }
    }

    private function showForm(Request $request, int $id): Response
    {
        $action = $request->getString('action');
        $error_message = $request->getString('error_message');
        if (empty($error_message)) {
            foreach (\App\Core\FlashMessage::all() as $fm) {
                if ($fm['type'] === 'danger') { $error_message = $fm['message']; break; }
            }
        }

        $userId = '';
        $documentType = '';
        $description = '';
        $issuedDate = '';
        $expiryDate = '';
        $documentFilename = '';

        if ($id > 0) {
            try {
                $document = $this->service->getDocument($id, $this->orgId);
                $userId = (string)$document->userId;
                $documentType = $document->documentType !== null ? (string)$document->documentType : '';
                $description = (string)$document->description;
                $issuedDate = $document->issuedDate !== null ? DateHelper::toDisplayDate($document->issuedDate) : '';
                $expiryDate = $document->expiryDate !== null ? DateHelper::toDisplayDate($document->expiryDate) : '';
                $documentFilename = (string)$document->filename;
            } catch (\Throwable $e) {
                $this->logError("UserDocumentController::showForm error: " . $e->getMessage());
                $error_message = $e->getMessage();
            }
        }

        $usersList = [];
        $documentCategories = [];
        try {
            $usersList = $this->db->fetchAll("SELECT id, full_name FROM `" . DB::USERS . "` WHERE is_active=1 ORDER BY full_name");
        } catch (\Throwable $e) {
            $this->logError("UserDocumentController::showForm error: " . $e->getMessage());
        }
        try {
            $documentCategories = $this->db->fetchAll("SELECT id, document_category FROM `" . DB::DOCUMENT_CATEGORIES . "` WHERE is_active=1 AND document_category_type='employees' ORDER BY CASE document_category WHEN 'Emirates ID' THEN 1 WHEN 'Visa' THEN 2 WHEN 'Labor Card' THEN 3 WHEN 'Passport' THEN 4 WHEN 'Photo' THEN 5 WHEN 'Contract' THEN 6 ELSE 7 END, document_category");
        } catch (\Throwable $e) {
            $this->logError("UserDocumentController::showForm error: " . $e->getMessage());
        }

        $uploadPath = '../uploads/user_documents/';

        return Response::html($this->view->render('user_documents/form.php', [
            'id' => $id,
            'module' => 'user_documents',
            'moduleCaption' => $this->moduleCaption,
            'moduleId' => $this->moduleId,
            'session_user_id' => $this->userId,
            'session_role_id' => $this->roleId,
            'error_message' => $error_message,
            'action' => $action,
            'user_id' => $userId,
            'document_type' => $documentType,
            'description' => $description,
            'issued_date' => $issuedDate,
            'expiry_date' => $expiryDate,
            'document_filename' => $documentFilename,
            'usersList' => $usersList,
            'documentCategories' => $documentCategories,
            'uploadPath' => $uploadPath,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
