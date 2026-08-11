<?php

declare(strict_types=1);

namespace App\Model;

readonly class AccountReportCategory
{
    public function __construct(
        public int $id,
        public string $categoryName = "",
        public string $description = "",
    public bool $publish = true,
    public bool $isActive = true,
    public int $createdBy = 0,
    public string $createdAt = "",
    public string $updatedAt = "",
    public int $updatedBy = 0,
    ) {}
}
