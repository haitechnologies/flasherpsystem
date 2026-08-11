<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Model\CustomerContact;
use App\Repository\CustomerContactRepository;
use App\Repository\CustomerRepository;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Security\DisposableEmailValidator;

class CustomerContactService
{
    private CustomerContactRepository $contactRepo;
    private CustomerRepository $customerRepo;
    private Database $db;
    private DisposableEmailValidator $emailValidator;

    public function __construct(
        CustomerContactRepository $contactRepo,
        CustomerRepository $customerRepo,
        Database $db,
        ?DisposableEmailValidator $emailValidator = null,
    ) {
        $this->contactRepo = $contactRepo;
        $this->customerRepo = $customerRepo;
        $this->db = $db;
        $this->emailValidator = $emailValidator ?? new DisposableEmailValidator();
    }

    public function getContact(int $id, int $orgId): CustomerContact
    {
        $contact = $this->contactRepo->find($id, $orgId);
        if ($contact === null) {
            throw new NotFoundException("Contact with ID {$id} not found.");
        }
        return $contact;
    }

    /**
     * @return CustomerContact[]
     */
    public function getContactsByCustomer(int $customerId, int $orgId): array
    {
        return $this->contactRepo->findByCustomer($customerId, $orgId);
    }

    public function createContact(array $data, int $orgId, int $userId): CustomerContact
    {
        $this->validateContactData($data, $orgId);

        $customerId = (int)($data['customer_id'] ?? 0);
        $this->ensureCustomerExists($customerId, $orgId);

        $isPrimary = (bool)($data['is_primary'] ?? false);

        $this->db->beginTransaction();
        try {
            if ($isPrimary) {
                $this->customerRepo->clearPrimaryContacts($customerId, $orgId);
            }

            $contact = new CustomerContact(
                id: null,
                organizationId: $orgId,
                isPrimary: $isPrimary,
                customerId: $customerId,
                firstName: trim((string)$data['first_name']),
                lastName: trim((string)$data['last_name']),
                position: !empty($data['position']) ? trim((string)$data['position']) : null,
                email: trim((string)$data['email']),
                phone: !empty($data['phone']) ? trim((string)$data['phone']) : null,
                notes: !empty($data['notes']) ? trim((string)$data['notes']) : null,
                publish: (bool)($data['publish'] ?? true),
                isActive: (bool)($data['is_active'] ?? true),
                createdBy: $userId,
            );

            $saved = $this->contactRepo->save($contact);
            $this->db->commit();

            return $saved;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateContact(int $id, array $data, int $orgId, int $userId): CustomerContact
    {
        $contact = $this->getContact($id, $orgId);
        $this->validateContactData($data, $orgId, $id);

        $isPrimary = isset($data['is_primary']) ? (bool)$data['is_primary'] : $contact->isPrimary;

        $this->db->beginTransaction();
        try {
            if ($isPrimary && !$contact->isPrimary) {
                $this->customerRepo->clearPrimaryContacts($contact->customerId, $orgId);
            }

            $updated = new CustomerContact(
                id: $contact->id,
                organizationId: $contact->organizationId,
                isPrimary: $isPrimary,
                customerId: $contact->customerId,
                firstName: isset($data['first_name']) ? trim((string)$data['first_name']) : $contact->firstName,
                lastName: isset($data['last_name']) ? trim((string)$data['last_name']) : $contact->lastName,
                position: isset($data['position']) ? (!empty($data['position']) ? trim((string)$data['position']) : null) : $contact->position,
                email: isset($data['email']) ? trim((string)$data['email']) : $contact->email,
                phone: isset($data['phone']) ? (!empty($data['phone']) ? trim((string)$data['phone']) : null) : $contact->phone,
                notes: isset($data['notes']) ? (!empty($data['notes']) ? trim((string)$data['notes']) : null) : $contact->notes,
                publish: isset($data['publish']) ? (bool)$data['publish'] : $contact->publish,
                isActive: isset($data['is_active']) ? (bool)$data['is_active'] : $contact->isActive,
                createdAt: $contact->createdAt,
                createdBy: $contact->createdBy,
                updatedBy: $userId,
            );

            $saved = $this->contactRepo->save($updated);
            $this->db->commit();

            return $saved;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteContact(int $id, int $orgId): bool
    {
        $this->getContact($id, $orgId);

        $this->db->beginTransaction();
        try {
            $result = $this->contactRepo->delete($id, $orgId);
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function ensureCustomerExists(int $customerId, int $orgId): void
    {
        $customer = $this->customerRepo->find($customerId, $orgId);
        if ($customer === null) {
            throw new NotFoundException("Customer with ID {$customerId} not found.");
        }
    }

    private function validateContactData(array $data, int $orgId, ?int $id = null): void
    {
        $errors = [];

        $existing = null;
        if ($id !== null) {
            $existing = $this->contactRepo->find($id, $orgId);
        }

        $firstName = isset($data['first_name']) ? trim($data['first_name']) : ($existing ? $existing->firstName : '');
        if ($firstName === '') {
            $errors['first_name'] = 'First name is mandatory.';
        }

        $lastName = isset($data['last_name']) ? trim($data['last_name']) : ($existing ? $existing->lastName : '');
        if ($lastName === '') {
            $errors['last_name'] = 'Last name is mandatory.';
        }

        $email = isset($data['email']) ? trim($data['email']) : ($existing ? $existing->email : '');
        if ($email === '') {
            $errors['email'] = 'Email is mandatory.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please provide a valid email address.';
        } else {
            $emailResult = $this->emailValidator->validate($email);
            if (!$emailResult[0]) {
                $errors['email'] = $emailResult[1];
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
