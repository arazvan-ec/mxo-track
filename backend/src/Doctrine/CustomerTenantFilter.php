<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\CustomerScopedEntityInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

final class CustomerTenantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->reflClass?->implementsInterface(CustomerScopedEntityInterface::class)) {
            return '';
        }

        $customerId = $this->getParameter('customer_id');

        return sprintf('%s.customer_id = %s', $targetTableAlias, $customerId);
    }
}
