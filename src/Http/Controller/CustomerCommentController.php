<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\EntityNoteService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class CustomerCommentController extends BaseController
{
    private EntityNoteService $service;
    private string $entityType = 'customer';

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        EntityNoteService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('customers', 'Comment');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id') ?: $request->getInt('comment_id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_customer_comments' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_customer_comments' && $this->canCreate()
                => $this->handleCreate($request),
            $request->isPost() && $action === 'delete_customer_comments' && $id > 0 && $this->canDelete()
                => $this->handleDelete($request, $id),
            default => $this->showForm($request, $id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $customerId = $request->getInt('customer_id');

        $data = [
            'entity_type' => $this->entityType,
            'entity_id' => $customerId,
            'notes' => $request->getString('comments'),
        ];

        try {
            $this->service->update($id, $data, $this->orgId, $this->userId);
            updateCustomerLogs($customerId, 'comment', 'updated');
            flash_success('The Comment has been updated successfully.');
            return Response::redirect("customer_comments.php?customer_id={$customerId}");
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("customer_comments.php?customer_id={$customerId}&action=edit_customer_comments&comment_id={$id}");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("customer_comments.php?customer_id={$customerId}&action=edit_customer_comments&comment_id={$id}");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'customer_comments',
                'module_slug' => 'customer_comments',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error('The Comment could not be updated. ' . $e->getMessage());
            return Response::redirect("customer_comments.php?customer_id={$customerId}&action=edit_customer_comments&comment_id={$id}");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $customerId = $request->getInt('customer_id');

        $data = [
            'entity_type' => $this->entityType,
            'entity_id' => $customerId,
            'notes' => $request->getString('comments'),
        ];

        try {
            $saved = $this->service->create($data, $this->orgId, $this->userId);
            updateCustomerLogs($customerId, 'comment', 'added');
            flash_success('The Comment has been saved successfully.');
            return Response::redirect("customer_comments.php?customer_id={$customerId}");
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("customer_comments.php?customer_id={$customerId}");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("customer_comments.php?customer_id={$customerId}");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'customer_comments',
                'module_slug' => 'customer_comments',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error('The Comment could not be saved. ' . $e->getMessage());
            return Response::redirect("customer_comments.php?customer_id={$customerId}");
        }
    }

    private function handleDelete(Request $request, int $id): Response
    {
        $customerId = $request->getInt('customer_id');

        try {
            $this->service->delete($id, $this->orgId);
            updateCustomerLogs($customerId, 'comments', 'deleted');
            flash_success('Comment Deleted Successfully.');
            return Response::redirect("customer_comments.php?customer_id={$customerId}");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'customer_comments',
                'module_slug' => 'customer_comments',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("customer_comments.php?customer_id={$customerId}");
        }
    }

    private function showForm(Request $request, int $id): Response
    {
        $customerId = $request->getInt('customer_id');
        $action = $request->getString('action');
        $commentId = $request->getInt('comment_id') ?: $id;
        $error_message = $request->getString('error_message');
        $success_message = $request->getString('success_message');

        $comments = '';
        $commentIdToEdit = 0;

        if ($action === 'edit_customer_comments' && $commentId > 0 && $customerId > 0) {
            try {
                $note = $this->service->getById($commentId, $this->orgId);
                if ($note->entityType === $this->entityType && $note->entityId === $customerId) {
                    $comments = $note->notes ?? '';
                    $commentIdToEdit = $commentId;
                }
            } catch (\Throwable $e) {
                log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                    'module' => 'customer_comments',
                    'module_slug' => 'customer_comments',
                    'stack_trace' => $e->getTraceAsString(),
                    'error_code' => (string)$e->getCode(),
                ]));
                $error_message = $e->getMessage();
            }
        }

        $allNotes = [];
        try {
            $allNotes = $this->service->getByEntity($this->entityType, $customerId, $this->orgId);
        } catch (\Throwable $e) {
        }

        $userNames = [];
        if (!empty($allNotes)) {
            $userIds = array_unique(array_map(fn($n) => $n->createdBy, $allNotes));
            $userIds = array_filter($userIds, fn($id) => $id > 0);
            if (!empty($userIds)) {
                $placeholders = implode(',', $userIds);
                try {
                    $rows = $this->db->fetchAll("SELECT id, full_name FROM `" . \App\Core\DB::USERS . "` WHERE id IN ({$placeholders})");
                    foreach ($rows as $row) {
                        $userNames[(int)$row['id']] = $row['full_name'] ?? 'Unknown';
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        return Response::html($this->view->render('customer_comments/form.php', [
            'id' => $id,
            'module' => 'customer_comments',
            'moduleCaption' => $this->moduleCaption,
            'moduleId' => $this->moduleId,
            'session_user_id' => $this->userId,
            'session_role_id' => $this->roleId,
            'error_message' => $error_message,
            'success_message' => $success_message,
            'customer_id' => $customerId,
            'comments' => $comments,
            'commentId' => $commentIdToEdit,
            'action' => $action,
            'allNotes' => $allNotes,
            'userNames' => $userNames,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
            'canDelete' => $this->canDelete(),
        ]));
    }
}
