<?php

declare(strict_types=1);

namespace App\Provider\Gps;

use App\Tracking\GpsPositionProviderInterface;

/**
 * GPS provider for webhook-based position ingestion.
 *
 * Positions are pushed via HTTP webhook rather than pulled from
 * a tracking server. Only implements GpsPositionProviderInterface
 * since device management is not applicable to webhook-based providers.
 */
final class WebhookGpsProvider implements GpsPositionProviderInterface
{
    public function getPositions(int $deviceId, ?\DateTimeImmutable $since = null): array
    {
        return [];
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
