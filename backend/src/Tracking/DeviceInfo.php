<?php

declare(strict_types=1);

namespace App\Tracking;

final readonly class DeviceInfo
{
    public function __construct(
        public int $id,
        public string $name,
        public string $uniqueId,
    ) {
    }
}
