<?php

declare(strict_types=1);

namespace App\Domain\Route\Model;

final readonly class StopMapView
{
    public function __construct(
        public int $sequence,
        public string $address,
        public ?float $lat,
        public ?float $lng,
        public string $status,
        public bool $isOrigin,
        public ?string $recipientName = null,
        public ?string $recipientPhone = null,
        public ?string $deliveredAt = null,
        public ?string $exceptionCode = null,
        public ?string $exceptionNotes = null,
        public ?int $etaMinutes = null,
        public ?string $etaTime = null,
        public ?float $etaDistanceKm = null,
        public ?string $shipmentPublicId = null,
    ) {}

    /**
     * Build from a RouteSnapshot stopStates entry + optional ETA data.
     *
     * @param array<string, mixed> $state
     * @param array{eta: string, minutes: int, distance_km: float}|null $etaData
     */
    public static function fromSnapshotState(array $state, ?array $etaData = null): self
    {
        $etaTime = null;
        if ($etaData !== null && isset($etaData['eta'])) {
            try {
                $etaTime = (new \DateTimeImmutable($etaData['eta']))->format('H:i');
            } catch (\Exception) {
                $etaTime = null;
            }
        }

        return new self(
            sequence: (int) ($state['sequence'] ?? 0),
            address: (string) ($state['address'] ?? ''),
            lat: isset($state['lat']) ? (float) $state['lat'] : null,
            lng: isset($state['lng']) ? (float) $state['lng'] : null,
            status: (string) ($state['status'] ?? 'PENDING'),
            isOrigin: (bool) ($state['isOrigin'] ?? false),
            recipientName: $state['recipientName'] ?? null,
            recipientPhone: $state['recipientPhone'] ?? null,
            deliveredAt: $state['deliveredAt'] ?? null,
            exceptionCode: $state['exceptionCode'] ?? null,
            exceptionNotes: $state['exceptionNotes'] ?? null,
            etaMinutes: $etaData['minutes'] ?? null,
            etaTime: $etaTime,
            etaDistanceKm: isset($etaData['distance_km']) ? (float) $etaData['distance_km'] : null,
            shipmentPublicId: $state['shipmentPublicId'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'sequence' => $this->sequence,
            'address' => $this->address,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'status' => $this->status,
            'isOrigin' => $this->isOrigin,
            'recipientName' => $this->recipientName,
            'recipientPhone' => $this->recipientPhone,
            'deliveredAt' => $this->deliveredAt,
            'exceptionCode' => $this->exceptionCode,
            'exceptionNotes' => $this->exceptionNotes,
            'etaMinutes' => $this->etaMinutes,
            'etaTime' => $this->etaTime,
            'etaDistanceKm' => $this->etaDistanceKm,
            'shipmentPublicId' => $this->shipmentPublicId,
        ], static fn (mixed $v) => $v !== null && $v !== false);
    }
}
