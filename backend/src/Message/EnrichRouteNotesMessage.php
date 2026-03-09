<?php

declare(strict_types=1);

namespace App\Message;

final class EnrichRouteNotesMessage
{
    public function __construct(
        private readonly int $routeId,
    ) {
    }

    public function getRouteId(): int
    {
        return $this->routeId;
    }
}
