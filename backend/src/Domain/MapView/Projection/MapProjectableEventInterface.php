<?php

declare(strict_types=1);

namespace App\Domain\MapView\Projection;

interface MapProjectableEventInterface
{
    public function getRoutePublicId(): string;

    public function getOccurredAt(): \DateTimeImmutable;
}
