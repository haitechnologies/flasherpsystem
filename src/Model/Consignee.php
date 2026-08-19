<?php

declare(strict_types=1);

namespace App\Model;

readonly class Consignee
{
    public function __construct(
        public int $id,
        public string $consigneeName = '',
        public string $addressLine1 = '',
        public string $addressLine2 = '',
        public string $city = '',
        public string $zipcode = '',
        public string $province = '',
        public int $country = 0,
        public string $email = '',
        public string $telephone = '',
        public string $mobile = '',
        public string $fax = '',
        public bool $isActive = true,
        public int $createdBy = 0,
        public string $createdAt = '',
    ) {}
}
