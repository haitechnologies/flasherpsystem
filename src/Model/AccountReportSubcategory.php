<?php

declare(strict_types=1);

namespace App\Model;

readonly class AccountReportSubcategory
{
    public function __construct(
        public int $id,
        public string $reportName = "",
        public string $description = "",
    public string $slug = "",
    public int $categoryId = 0,
    public int $ordering = 0,
    public bool $isCompleted = false,
    public string $lastVisited = "",
    public bool $publish = true,
    public bool $isActive = true,
    public int $createdBy = 0,
    public string $createdAt = "",
    public string $updatedAt = "",
    public int $updatedBy = 0,
    ) {}
}
