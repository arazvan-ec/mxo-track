<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_customer_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class Customer
{
    use PublicIdTrait;

    #[ORM\Column(length: 150)]
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string { return $this->name; }
}
