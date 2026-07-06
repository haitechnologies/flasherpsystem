<?php

declare(strict_types=1);

namespace App\Model;

readonly class Item
{
    public function __construct(
        public int $id,
        public string $itemType = 'services',
        public string $itemName = '',
        public string $unitPrice = '0',
        public string $sellingPrice = '0',
        public int $taxTreatmentId = 0,
        public bool $isExcise = false,
        public bool $isActive = true,
        public int $createdBy = 0,
        public string $createdAt = '',
        public int $saleAccount = 0,
        public string $saleDescription = '',
        public int $purchaseAccount = 0,
        public string $purchaseDescription = '',
        public string $costPrice = '0',
        public int $preferredVendorId = 0,
    ) {}
}
