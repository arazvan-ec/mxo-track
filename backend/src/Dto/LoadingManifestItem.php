<?php

declare(strict_types=1);

namespace App\Dto;

final class LoadingManifestItem
{
    public function __construct(
        public readonly int $loadingOrder,
        public readonly int $deliverySequence,
        public readonly string $shipmentPublicId,
        public readonly string $shipmentReference,
        public readonly ?string $recipientName,
        public readonly string $address,
        public readonly ?string $recipientPhone,
        public readonly ?float $weightKg,
        public readonly ?float $volumeM3,
        public readonly ?int $parcels,
    ) {}
}
