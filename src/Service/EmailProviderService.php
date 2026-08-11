<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\EmailProvider;
use App\Repository\EmailProviderRepository;
use App\Exception\ValidationException;

class EmailProviderService
{
    private EmailProviderRepository $repo;

    public function __construct(EmailProviderRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getById(int $id): ?EmailProvider
    {
        return $this->repo->find($id);
    }

    public function getByEmail(string $email): ?EmailProvider
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        foreach ($this->repo->findAll() as $provider) {
            if ($provider->isActive && strtolower(trim($provider->email)) === $email) {
                return $provider;
            }
        }
        return null;
    }

    public function getDefault(): ?EmailProvider
    {
        foreach ($this->repo->findAll() as $provider) {
            if ($provider->isActive && $provider->isPrimary) {
                return $provider;
            }
        }
        foreach ($this->repo->findAll() as $provider) {
            if ($provider->isActive) {
                return $provider;
            }
        }
        return null;
    }

    public function getAvailableWithQuota(array $exclude = []): ?EmailProvider
    {
        $exclude = array_map('intval', $exclude);
        foreach ($this->repo->findAll() as $provider) {
            if (!$provider->isActive) {
                continue;
            }
            if ($provider->id !== null && in_array($provider->id, $exclude, true)) {
                continue;
            }
            return $provider;
        }
        return null;
    }

    public function list(): array
    {
        return $this->repo->findAll();
    }

    public function create(array $data, int $createdBy): int
    {
        $name = trim((string)($data['provider_name'] ?? ''));
        if ($name === '') {
            throw new ValidationException(['provider_name' => 'Provider name is mandatory.']);
        }
        if ($this->repo->exists($name)) {
            throw new ValidationException(['provider_name' => 'Provider name already exists.']);
        }

        $item = new EmailProvider(
            id: 0,
            providerName: $name,
            emailEncryption: (string)($data['email_encryption'] ?? 'NONE'),
            smtpHost: (string)($data['smtp_host'] ?? ''),
            smtpPort: (string)($data['smtp_port'] ?? ''),
            email: (string)($data['email'] ?? ''),
            smtpUsername: (string)($data['smtp_username'] ?? ''),
            smtpPassword: (string)($data['smtp_password'] ?? ''),
            isActive: (bool)($data['is_active'] ?? true),
            isPrimary: (bool)($data['is_primary'] ?? false),
            createdBy: $createdBy,
        );

        return $this->repo->insert($item);
    }

    public function update(int $id, array $data, int $updatedBy): bool
    {
        $existing = $this->repo->find($id);
        if ($existing === null) {
            return false;
        }

        $name = trim((string)($data['provider_name'] ?? $existing->providerName));
        if ($name === '') {
            throw new ValidationException(['provider_name' => 'Provider name is mandatory.']);
        }
        if ($this->repo->exists($name, $id)) {
            throw new ValidationException(['provider_name' => 'Provider name already exists.']);
        }

        return $this->repo->update($id, [
            'provider_name' => $name,
            'email_encryption' => (string)($data['email_encryption'] ?? $existing->emailEncryption),
            'smtp_host' => (string)($data['smtp_host'] ?? $existing->smtpHost),
            'smtp_port' => (string)($data['smtp_port'] ?? $existing->smtpPort),
            'email' => (string)($data['email'] ?? $existing->email),
            'smtp_username' => (string)($data['smtp_username'] ?? $existing->smtpUsername),
            'smtp_password' => (string)($data['smtp_password'] ?? $existing->smtpPassword),
            'is_active' => (bool)($data['is_active'] ?? $existing->isActive) ? 1 : 0,
            'is_primary' => (bool)($data['is_primary'] ?? $existing->isPrimary) ? 1 : 0,
            'updated_by' => $updatedBy,
        ]);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
