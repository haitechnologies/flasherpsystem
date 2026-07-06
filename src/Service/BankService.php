<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Bank;
use App\Repository\BankRepository;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;

class BankService
{
    private BankRepository $bankRepo;

    public function __construct(BankRepository $bankRepo)
    {
        $this->bankRepo = $bankRepo;
    }

    public function getById(int $id, int $orgId): Bank
    {
        $bank = $this->bankRepo->find($id, $orgId);
        if ($bank === null) {
            throw new NotFoundException("Bank with ID {$id} not found.");
        }
        return $bank;
    }

    public function create(array $data, int $orgId, int $userId): Bank
    {
        $accountName = trim((string)($data['account_name'] ?? ''));
        if ($accountName === '') {
            throw new ValidationException(['account_name' => 'Account name is mandatory.']);
        }

        if ($this->bankRepo->existsByName($accountName, $orgId)) {
            throw new ValidationException(['account_name' => 'Duplicate Bank Account. Please enter different.']);
        }

        $bank = new Bank(
            id: null,
            organizationId: $orgId,
            accountName: $accountName,
            accountCode: trim((string)($data['account_code'] ?? '')),
            openingBalance: (float)($data['opening_balance'] ?? 0.00),
            accountId: !empty($data['account_id']) ? (int)$data['account_id'] : null,
            currency: (int)($data['currency'] ?? 0),
            bankName: trim((string)($data['bank_name'] ?? '')),
            branch: trim((string)($data['branch'] ?? '')),
            address: trim((string)($data['address'] ?? '')),
            iban: trim((string)($data['iban'] ?? '')),
            routingNumber: trim((string)($data['routing_number'] ?? '')),
            description: trim((string)($data['description'] ?? '')),
            isPrimary: !empty($data['is_primary']),
            isActive: !empty($data['publish'] ?? $data['is_active'] ?? true),
            createdBy: $userId
        );

        return $this->bankRepo->save($bank);
    }

    public function update(int $id, array $data, int $orgId, int $userId): Bank
    {
        $bank = $this->getById($id, $orgId);

        $accountName = trim((string)($data['account_name'] ?? $bank->accountName));
        if ($accountName === '') {
            throw new ValidationException(['account_name' => 'Account name is mandatory.']);
        }

        if ($accountName !== $bank->accountName && $this->bankRepo->existsByName($accountName, $orgId, $id)) {
            throw new ValidationException(['account_name' => 'Duplicate Bank Account. Please enter different.']);
        }

        $updatedBank = new Bank(
            id: $bank->id,
            organizationId: $orgId,
            accountName: $accountName,
            accountCode: trim((string)($data['account_code'] ?? $bank->accountCode)),
            openingBalance: isset($data['opening_balance']) ? (float)$data['opening_balance'] : $bank->openingBalance,
            accountId: isset($data['account_id']) ? (!empty($data['account_id']) ? (int)$data['account_id'] : null) : $bank->accountId,
            currency: (int)($data['currency'] ?? $bank->currency),
            bankName: trim((string)($data['bank_name'] ?? $bank->bankName)),
            branch: trim((string)($data['branch'] ?? $bank->branch)),
            address: trim((string)($data['address'] ?? $bank->address)),
            iban: trim((string)($data['iban'] ?? $bank->iban)),
            routingNumber: trim((string)($data['routing_number'] ?? $bank->routingNumber)),
            description: trim((string)($data['description'] ?? $bank->description)),
            isPrimary: isset($data['is_primary']) ? !empty($data['is_primary']) : $bank->isPrimary,
            isActive: isset($data['publish']) ? !empty($data['publish']) : (isset($data['is_active']) ? !empty($data['is_active']) : $bank->isActive),
            createdAt: $bank->createdAt,
            updatedAt: null,
            updatedBy: $userId,
            createdBy: $bank->createdBy
        );

        return $this->bankRepo->save($updatedBank);
    }

    public function delete(int $id, int $orgId): void
    {
        $this->getById($id, $orgId);
        $this->bankRepo->delete($id, $orgId);
    }

    public function list(int $orgId): array
    {
        return $this->bankRepo->findAll($orgId);
    }
}
