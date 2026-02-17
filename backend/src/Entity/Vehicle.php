<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle')]
class Vehicle
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(nullable: true)]
    private ?int $traccarDeviceId = null;

    #[ORM\Column]
    private bool $isActive = true;

    public function __construct(string $name)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
    }

    public function getId(): Uuid { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getTraccarDeviceId(): ?int { return $this->traccarDeviceId; }
    public function setTraccarDeviceId(?int $id): void { $this->traccarDeviceId = $id; }
    public function isActive(): bool { return $this->isActive; }
}
