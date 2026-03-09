<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched when a route status changes to DONE.
 * Triggers AI-powered post-route analysis.
 */
final readonly class PostRouteAnalysisMessage
{
    public function __construct(
        private string $routePublicId,
    ) {
    }

    public function getRoutePublicId(): string
    {
        return $this->routePublicId;
    }
}
