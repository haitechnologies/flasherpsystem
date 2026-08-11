<?php

declare(strict_types=1);

namespace App\Model;

readonly class EmailProvider
{
    public function __construct(
        public int $id,
        public string $providerName = '',
        public string $emailEncryption = 'NONE',
        public string $smtpHost = '',
        public string $smtpPort = '',
        public string $email = '',
        public string $smtpUsername = '',
        public string $smtpPassword = '',
        public bool $isActive = true,
        public bool $isPrimary = false,
        public int $createdBy = 0,
        public string $createdAt = '',
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider_name' => $this->providerName,
            'email_encryption' => $this->emailEncryption,
            'smtp_host' => $this->smtpHost,
            'smtp_port' => $this->smtpPort,
            'email' => $this->email,
            'smtp_username' => $this->smtpUsername,
            'smtp_password' => $this->smtpPassword,
            'is_active' => $this->isActive ? 1 : 0,
            'is_primary' => $this->isPrimary ? 1 : 0,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
        ];
    }
}
