<?php

declare(strict_types=1);

namespace App\Dto\Api;

use App\Domain\Shipment\Model\Shipment;

final readonly class ShipmentResponse
{
    private function __construct(
        public string $publicId,
        public string $reference,
        public ?string $recipientName,
        public ?string $recipientPhone,
        public ?string $address,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $notes,
        public ?string $description,
        public string $serviceType,
        public ?float $totalWeightKg,
        public ?float $totalVolumeM3,
        public ?int $totalParcels,
        public ?string $trackingToken,
        public string $createdAt,
    ) {}

    public static function fromEntity(Shipment $shipment): self
    {
        return new self(
            publicId: $shipment->getPublicIdString(),
            reference: $shipment->getReference(),
            recipientName: $shipment->getRecipientName(),
            recipientPhone: $shipment->getRecipientPhone(),
            address: $shipment->getAddress(),
            latitude: $shipment->getLatitude(),
            longitude: $shipment->getLongitude(),
            notes: $shipment->getNotes(),
            description: $shipment->getDescription(),
            serviceType: $shipment->getServiceType()->value,
            totalWeightKg: $shipment->getTotalWeightKg(),
            totalVolumeM3: $shipment->getTotalVolumeM3(),
            totalParcels: $shipment->getTotalParcels(),
            trackingToken: $shipment->getTrackingToken(),
            createdAt: $shipment->getCreatedAt()->format(\DATE_ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'public_id' => $this->publicId,
            'reference' => $this->reference,
            'recipient_name' => $this->recipientName,
            'recipient_phone' => $this->recipientPhone,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'notes' => $this->notes,
            'description' => $this->description,
            'service_type' => $this->serviceType,
            'total_weight_kg' => $this->totalWeightKg,
            'total_volume_m3' => $this->totalVolumeM3,
            'total_parcels' => $this->totalParcels,
            'tracking_token' => $this->trackingToken,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * Compact version for list endpoints.
     *
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'public_id' => $this->publicId,
            'reference' => $this->reference,
            'address' => $this->address,
            'recipient_name' => $this->recipientName,
            'tracking_token' => $this->trackingToken,
            'created_at' => $this->createdAt,
        ];
    }
}
