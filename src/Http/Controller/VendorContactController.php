<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\VendorService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class VendorContactController extends BaseController
{
    private VendorService $vendorService;

    public function __construct(
        Database $db,
        VendorService $vendorService,
        int $userId = 0,
        int $roleId = 0,
        int $orgId = 0,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->vendorService = $vendorService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('vendor_contacts', 'Vendor Contact');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $vendorId = $request->getInt('vendor_id');
        $contactId = $request->getInt('contact_id');
        $action = $request->getString('action');

        if ($vendorId <= 0) {
            flash_error('Vendor ID is required.');
            return Response::redirect('listing_vendors.php');
        }

        try {
            $this->vendorService->getVendor($vendorId, $this->orgId);
        } catch (NotFoundException $e) {
            return Response::redirect('listing_vendors.php');
        }

        // IDOR: verify ownership
        $vendorsModuleId = (int)($this->db->fetchOne(
            "SELECT id FROM erp_modules WHERE slug = 'vendors' LIMIT 1"
        )['id'] ?? 0);
        if (!granted('view', $vendorsModuleId) && $this->roleId !== Roles::SYSTEM_ADMIN) {
            $vendorObj = $this->vendorService->getVendor($vendorId, $this->orgId);
            $isOwner = (int)$vendorObj->createdBy === $this->userId || (int)$vendorObj->vendorOwner === $this->userId;
            if (!$isOwner) {
                flash_error('Access denied');
                return Response::redirect('listing_vendors.php');
            }
        }

        return match (true) {
            $request->isPost() && $action === 'delete_vendor_contacts' && $contactId > 0 && $this->canDelete()
                => $this->handleDelete($request, $vendorId, $contactId),
            $request->isPost() && $action === 'update_vendor_contacts' && $contactId > 0 && $this->canEdit()
                => $this->handleUpdate($request, $vendorId, $contactId),
            $request->isPost() && $action === 'add_vendor_contacts' && $this->canCreate()
                => $this->handleCreate($request, $vendorId),
            default => $this->showForm($request, $vendorId, $contactId),
        };
    }

    private function handleUpdate(Request $request, int $vendorId, int $contactId): Response
    {
        $contactData = [
            'first_name' => $request->getString('first_name'),
            'last_name' => $request->getString('last_name'),
            'position' => $request->getString('position'),
            'email' => $request->getString('email'),
            'phone' => $request->getString('phone'),
            'notes' => $request->getString('notes'),
        ];

        try {
            $this->vendorService->updateContact($contactId, $contactData, $this->orgId, $this->userId);

            updateVendorLogs($vendorId, 'contact', 'edit', $contactId);
            flash_success('The Vendor Contact has been updated successfully.');
            return Response::redirect('vendor_overview.php?vendor_id=' . $vendorId);
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return $this->showForm($request, $vendorId, $contactId, $contactData, $error);
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => $this->moduleSlug,
                'module_slug' => $this->moduleSlug,
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect('vendor_contacts.php?vendor_id=' . $vendorId . '&contact_id=' . $contactId . '&action=edit_vendor_contacts');
        }
    }

    private function handleCreate(Request $request, int $vendorId): Response
    {
        $contactData = [
            'first_name' => $request->getString('first_name'),
            'last_name' => $request->getString('last_name'),
            'position' => $request->getString('position'),
            'email' => $request->getString('email'),
            'phone' => $request->getString('phone'),
            'notes' => $request->getString('notes'),
            'is_primary' => false,
        ];

        try {
            $this->vendorService->createContact([
                'vendor_id' => $vendorId,
                ...$contactData,
            ], $this->orgId, $this->userId);

            updateVendorLogs($vendorId, 'contact', 'add', 0);
            flash_success('The Vendor Contact has been saved successfully.');
            return Response::redirect('vendor_overview.php?vendor_id=' . $vendorId);
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return $this->showForm($request, $vendorId, 0, $contactData, $error);
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => $this->moduleSlug,
                'module_slug' => $this->moduleSlug,
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect('vendor_contacts.php?vendor_id=' . $vendorId);
        }
    }

    private function handleDelete(Request $request, int $vendorId, int $contactId): Response
    {
        try {
            $contact = $this->vendorService->getContactsByVendor($vendorId, $this->orgId);

            $matched = null;
            foreach ($contact as $c) {
                if ((int)$c->id === $contactId) {
                    $matched = $c;
                    break;
                }
            }

            if ($matched === null) {
                flash_error('Contact does not belong to this vendor.');
                return Response::redirect('vendor_contacts.php?vendor_id=' . $vendorId);
            }

            // Authorization: only creator or superadmin can delete
            if ($this->roleId !== Roles::SYSTEM_ADMIN && $matched->createdBy !== $this->userId) {
                flash_error('Access denied.');
                return Response::redirect('vendor_contacts.php?vendor_id=' . $vendorId);
            }

            $this->vendorService->deleteContact($contactId, $this->orgId);

            updateVendorLogs($vendorId, 'contact', 'delete', $contactId);
            flash_success('Vendor Contact Deleted Successfully.');
            return Response::redirect('vendor_overview.php?vendor_id=' . $vendorId);
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => $this->moduleSlug,
                'module_slug' => $this->moduleSlug,
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect('vendor_contacts.php?vendor_id=' . $vendorId);
        }
    }

    private function showForm(Request $request, int $vendorId, int $contactId, ?array $prefillData = null, ?string $prefillError = null): Response
    {
        $module = 'vendor_contacts';
        $moduleCaption = $this->moduleCaption;
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;
        $session_role_id = $this->roleId;
        $flashMessages = \App\Core\FlashMessage::all();
        $error_message = $prefillError ?? $request->getString('error_message');
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
        $action = $request->getString('action');

        $first_name = '';
        $last_name = '';
        $position = '';
        $email = '';
        $phone = '';
        $notes = '';

        // Prefill from validation errors
        if ($prefillData !== null) {
            $first_name = (string)($prefillData['first_name'] ?? '');
            $last_name = (string)($prefillData['last_name'] ?? '');
            $position = (string)($prefillData['position'] ?? '');
            $email = (string)($prefillData['email'] ?? '');
            $phone = (string)($prefillData['phone'] ?? '');
            $notes = (string)($prefillData['notes'] ?? '');
        }

        if ($contactId > 0 && ($action === 'edit_vendor_contacts' || $action === 'update_vendor_contacts')) {
            try {
                $contacts = $this->vendorService->getContactsByVendor($vendorId, $this->orgId);
                foreach ($contacts as $contact) {
                    if ((int)$contact->id === $contactId) {
                        $first_name = $contact->firstName;
                        $last_name = $contact->lastName;
                        $position = (string)$contact->position;
                        $email = $contact->email;
                        $phone = (string)$contact->phone;
                        $notes = (string)$contact->notes;
                    }
                }
            } catch (NotFoundException $e) {
                $error_message = $e->getMessage();
            }
        }

        return Response::html($this->view->render('vendor_contacts/form.php', [
            'vendor_id' => $vendorId,
            'contact_id' => $contactId,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'success_message' => $success_message,
            'action' => $action,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'position' => $position,
            'email' => $email,
            'phone' => $phone,
            'notes' => $notes,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
            'canDelete' => $this->canDelete(),
        ]));
    }
}
