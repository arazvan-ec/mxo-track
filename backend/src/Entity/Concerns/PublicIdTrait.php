<?php

declare(strict_types=1);

namespace App\Entity\Concerns;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

trait PublicIdTrait
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?string $id = null;

    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $publicId;

    #[ORM\PrePersist]
    public function initializePublicId(): void
    {
        $this->publicId ??= new Ulid();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getPublicId(): Ulid
    {
        return $this->publicId;
    }

    public function getPublicIdString(): string
    {
        return (string) $this->publicId;
    }
}
