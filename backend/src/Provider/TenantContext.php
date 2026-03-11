<?php
declare(strict_types=1);
namespace App\Provider;

use App\Entity\Customer;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class TenantContext
{
    public function __construct(private Security $security) {}

    public function getCustomer(): ?Customer
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            return $user->getCustomer();
        }
        return null;
    }
}
