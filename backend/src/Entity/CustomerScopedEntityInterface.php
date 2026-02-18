<?php

declare(strict_types=1);

namespace App\Entity;

interface CustomerScopedEntityInterface
{
    public function getCustomer(): Customer;
}
