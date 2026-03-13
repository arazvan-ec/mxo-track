<?php

declare(strict_types=1);

namespace App\View;

final class StopViewData
{
    public function __construct(
        public readonly int $sequence,
        public readonly string $address,
        public readonly ?string $recipientName,
        public readonly ?string $recipientPhone,
        public readonly ?float $lat,
        public readonly ?float $lng,
        public readonly string $status,
        public readonly bool $isOrigin,
        public readonly ?string $deliveredAt = null,
        public readonly ?string $exceptionCode = null,
        public readonly ?string $exceptionNotes = null,
        public readonly ?int $etaMinutes = null,
        public readonly ?string $etaTime = null,
        public readonly ?float $etaDistanceKm = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'sequence' => $this->sequence,
            'address' => $this->address,
            'recipientName' => $this->recipientName,
            'recipientPhone' => $this->recipientPhone,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'status' => $this->status,
            'isOrigin' => $this->isOrigin,
            'deliveredAt' => $this->deliveredAt,
            'exceptionCode' => $this->exceptionCode,
            'exceptionNotes' => $this->exceptionNotes,
            'etaMinutes' => $this->etaMinutes,
            'etaTime' => $this->etaTime,
            'etaDistanceKm' => $this->etaDistanceKm,
        ], static fn($v) => $v !== null);
    }
}
