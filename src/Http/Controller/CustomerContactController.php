<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\CustomerContactService;
use App\Service\CustomerService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class CustomerContactController extends BaseController
{
    private CustomerContactService $contactService;
    private CustomerService $customerService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        CustomerContactService $contactService,
        CustomerService $customerService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->contactService = $contactService;
        $this->customerService = $customerService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('customer_contacts', 'Customer Contact');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $customerId = $request->getInt('customer_id');
        $contactId = $request->getInt('contact_id');
        $action = $request->getString('action');

        if ($customerId <= 0) {
            flash_error('Customer ID is required.');
            return Response::redirect('listing_customers.php');
        }

        try {
            $this->customerService->getCustomer($customerId, $this->orgId);
        } catch (NotFoundException $e) {
            return Response::redirect('listing_customers.php');
        }

        // IDOR: verify ownership
        $customersModuleId = (int)($this->db->fetchOne(
            "SELECT id FROM erp_modules WHERE slug = 'customers' LIMIT 1"
        )['id'] ?? 0);
        if (!granted('view', $customersModuleId) && $this->roleId !== Roles::SYSTEM_ADMIN) {
            $customerObj = $this->customerService->getCustomer($customerId, $this->orgId);
            $isOwner = (int)$customerObj->createdBy === $this->userId || (int)$customerObj->customerOwner === $this->userId;
            if (!$isOwner) {
                flash_error('Access denied');
                return Response::redirect('listing_customers.php');
            }
        }

        return match (true) {
            $request->isPost() && $action === 'delete_customer_contacts' && $contactId > 0 && $this->canDelete()
                => $this->handleDelete($request, $customerId, $contactId),
            $request->isPost() && $action === 'update_customer_contacts' && $contactId > 0 && $this->canEdit()
                => $this->handleUpdate($request, $customerId, $contactId),
            $request->isPost() && $action === 'add_customer_contacts' && $this->canCreate()
                => $this->handleCreate($request, $customerId),
            default => $this->showForm($request, $customerId, $contactId),
        };
    }

    private function handleUpdate(Request $request, int $customerId, int $contactId): Response
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
            $this->contactService->updateContact($contactId, $contactData, $this->orgId, $this->userId);

            updateCustomerLogs($customerId, 'contact', 'edit', $contactId);
            flash_success('The Customer Contact has been updated successfully.');
            return Response::redirect('customer_overview.php?customer_id=' . $customerId);
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return $this->showForm($request, $customerId, $contactId, $contactData, $error);
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => $this->moduleSlug,
                'module_slug' => $this->moduleSlug,
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect('customer_contacts.php?customer_id=' . $customerId . '&contact_id=' . $contactId . '&action=edit_customer_contacts');
        }
    }

    private function handleCreate(Request $request, int $customerId): Response
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
            $this->contactService->createContact([
                'customer_id' => $customerId,
                ...$contactData,
            ], $this->orgId, $this->userId);

            updateCustomerLogs($customerId, 'contact', 'add', 0);
            flash_success('The Customer Contact has been saved successfully.');
            return Response::redirect('customer_overview.php?customer_id=' . $customerId);
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return $this->showForm($request, $customerId, 0, $contactData, $error);
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => $this->moduleSlug,
                'module_slug' => $this->moduleSlug,
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect('customer_contacts.php?customer_id=' . $customerId);
        }
    }

    private function handleDelete(Request $request, int $customerId, int $contactId): Response
    {
        try {
            $contact = $this->contactService->getContact($contactId, $this->orgId);

            if ($contact->customerId !== $customerId) {
                flash_error('Contact does not belong to this customer.');
                return Response::redirect('customer_contacts.php?customer_id=' . $customerId);
            }

            // Authorization: only creator or superadmin can delete
            if ($this->roleId !== Roles::SYSTEM_ADMIN && $contact->createdBy !== $this->userId) {
                flash_error('Access denied.');
                return Response::redirect('customer_contacts.php?customer_id=' . $customerId);
            }

            $this->contactService->deleteContact($contactId, $this->orgId);

            updateCustomerLogs($customerId, 'contact', 'delete', $contactId);
            flash_success('Customer Contact Deleted Successfully.');
            return Response::redirect('customer_overview.php?customer_id=' . $customerId);
        } catch (\Throwable $e) {
            log_error($e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => $this->moduleSlug,
                'module_slug' => $this->moduleSlug,
                'stack_trace' => $e->getTraceAsString(),
                'error_code' => (string)$e->getCode(),
            ]));
            flash_error($e->getMessage());
            return Response::redirect('customer_contacts.php?customer_id=' . $customerId);
        }
    }

    private function showForm(Request $request, int $customerId, int $contactId, ?array $prefillData = null, ?string $prefillError = null): Response
    {
        $module = 'customer_contacts';
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

        if ($contactId > 0 && ($action === 'edit_customer_contacts' || $action === 'update_customer_contacts')) {
            try {
                $contact = $this->contactService->getContact($contactId, $this->orgId);
                if ($contact->customerId !== $customerId) {
                    $error_message = 'Contact does not belong to this customer.';
                } else {
                    $first_name = $contact->firstName;
                    $last_name = $contact->lastName;
                    $position = (string)$contact->position;
                    $email = $contact->email;
                    $phone = (string)$contact->phone;
                    $notes = (string)$contact->notes;
                }
            } catch (NotFoundException $e) {
                $error_message = $e->getMessage();
            }
        }

        return Response::html($this->view->render('customer_contacts/form.php', [
            'customer_id' => $customerId,
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
