<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\EntityNoteService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class LeadNoteController extends BaseController
{
    private EntityNoteService $service;
    private string $entityType = 'lead';

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
        $this->requiresModule('lead_notes', 'Note');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id') ?: $request->getInt('note_id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_lead_notes' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_lead_notes' && $this->canCreate()
                => $this->handleCreate($request),
            in_array($action, ['delete_lead_notes'], true) && $id > 0 && $this->canDelete()
                => $this->handleDelete($request, $id),
            default => $this->showForm($request, $id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $leadId = $request->getInt('lead_id');

        $data = [
            'entity_type' => $this->entityType,
            'entity_id' => $leadId,
            'notes' => $request->getString('notes'),
        ];

        try {
            $this->service->update($id, $data, $this->orgId, $this->userId);
            updateLeadLogs($leadId, 'note', $id, 'updated');
            flash_success('The Note has been updated successfully.');
            return Response::redirect("lead_notes.php?lead_id={$leadId}");
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("lead_notes.php?lead_id={$leadId}&action=edit_lead_notes&note_id={$id}");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("lead_notes.php?lead_id={$leadId}&action=edit_lead_notes&note_id={$id}");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'lead_notes',
                'module_slug' => 'lead_notes',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error('The Note could not be updated. ' . $e->getMessage());
            return Response::redirect("lead_notes.php?lead_id={$leadId}&action=edit_lead_notes&note_id={$id}");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $leadId = $request->getInt('lead_id');

        $data = [
            'entity_type' => $this->entityType,
            'entity_id' => $leadId,
            'notes' => $request->getString('notes'),
        ];

        try {
            $saved = $this->service->create($data, $this->orgId, $this->userId);
            updateLeadLogs($leadId, 'note', $saved->id ?? 0, 'added');
            flash_success('The Note has been saved successfully.');
            return Response::redirect("lead_notes.php?lead_id={$leadId}");
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("lead_notes.php?lead_id={$leadId}");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("lead_notes.php?lead_id={$leadId}");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'lead_notes',
                'module_slug' => 'lead_notes',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error('The Note could not be saved. ' . $e->getMessage());
            return Response::redirect("lead_notes.php?lead_id={$leadId}");
        }
    }

    private function handleDelete(Request $request, int $id): Response
    {
        $leadId = $request->getInt('lead_id');

        try {
            $this->service->delete($id, $this->orgId);
            updateLeadLogs($leadId, 'note', $id, 'deleted');
            flash_success('Item deleted successfully.');
            return Response::redirect("lead_notes.php?lead_id={$leadId}");
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => 'lead_notes',
                'module_slug' => 'lead_notes',
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect("lead_notes.php?lead_id={$leadId}");
        }
    }

    private function showForm(Request $request, int $id): Response
    {
        $leadId = $request->getInt('lead_id');
        $action = $request->getString('action');
        $noteIdToEdit = $request->getInt('note_id') ?: $id;
        $flashMessages = \App\Core\FlashMessage::all();
        $error_message = $request->getString('error_message');
        if (empty($error_message)) {
            foreach ($flashMessages as $fm) {
                if ($fm['type'] === 'danger') { $error_message = $fm['message']; break; }
            }
        }
        $success_message = $request->getString('success_message');
        if (empty($success_message)) {
            foreach ($flashMessages as $fm) {
                if ($fm['type'] === 'success') { $success_message = $fm['message']; break; }
            }
        }

        $notes = '';

        if ($action === 'edit_lead_notes' && $noteIdToEdit > 0 && $leadId > 0) {
            try {
                $note = $this->service->getById($noteIdToEdit, $this->orgId);
                if ($note->entityType === $this->entityType && $note->entityId === $leadId) {
                    $notes = $note->notes ?? '';
                }
            } catch (\Throwable $e) {
                log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                    'module' => 'lead_notes',
                    'module_slug' => 'lead_notes',
                    'stack_trace' => $e->getTraceAsString(),
                    'error_code' => (string)$e->getCode(),
                ]));
                $error_message = $e->getMessage();
            }
        }

        $allNotes = [];
        try {
            $allNotes = $this->service->getByEntity($this->entityType, $leadId, $this->orgId);
        } catch (\Throwable $e) {
        }

        $userNames = [];
        if (!empty($allNotes)) {
            $userIds = array_unique(array_map(fn($n) => $n->createdBy, $allNotes));
            $userIds = array_filter($userIds, fn($uid) => $uid > 0);
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

        return Response::html($this->view->render('lead_notes/form.php', [
            'id' => $id,
            'module' => 'lead_notes',
            'moduleCaption' => $this->moduleCaption,
            'moduleId' => $this->moduleId,
            'session_user_id' => $this->userId,
            'session_role_id' => $this->roleId,
            'error_message' => $error_message,
            'success_message' => $success_message,
            'lead_id' => $leadId,
            'notes' => $notes,
            'noteId' => $noteIdToEdit,
            'action' => $action,
            'allNotes' => $allNotes,
            'userNames' => $userNames,
            'canView' => $this->canView(),
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
            'canDelete' => $this->canDelete(),
        ]));
    }
}
