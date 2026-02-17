<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class Customer
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 150)]
    private string $name;

    public function __construct(string $name)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
    }

    public function getId(): Uuid { return $this->id; }
    public function getName(): string { return $this->name; }
}
