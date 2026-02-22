<?php

declare(strict_types=1);

namespace App\Entity;

interface SoftDeletableInterface
{
    public function getDeletedAt(): ?\DateTimeImmutable;

    public function isDeleted(): bool;

    public function softDelete(): void;
}
