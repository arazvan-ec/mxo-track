<?php

declare(strict_types=1);

namespace App\Dto\Api;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateShipmentRequest
{
    #[Assert\NotBlank(message: 'Reference is required.')]
    #[Assert\Length(max: 100)]
    public string $reference = '';

    #[Assert\Length(max: 255)]
    public ?string $address = null;

    #[Assert\Length(max: 100)]
    public ?string $recipientName = null;

    #[Assert\Regex(pattern: '/^\+?[0-9\s\-]{6,20}$/', message: 'Invalid phone format.')]
    public ?string $recipientPhone = null;

    #[Assert\Length(max: 500)]
    public ?string $notes = null;

    #[Assert\Length(max: 500)]
    public ?string $description = null;

    #[Assert\Range(min: -90, max: 90)]
    public ?float $latitude = null;

    #[Assert\Range(min: -180, max: 180)]
    public ?float $longitude = null;

    #[Assert\PositiveOrZero]
    public ?float $totalWeightKg = null;

    #[Assert\PositiveOrZero]
    public ?float $totalVolumeM3 = null;

    #[Assert\PositiveOrZero]
    public ?int $totalParcels = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->reference = \is_string($data['reference'] ?? null) ? $data['reference'] : '';
        $dto->address = isset($data['address']) && \is_string($data['address']) ? $data['address'] : null;
        $dto->recipientName = isset($data['recipient_name']) && \is_string($data['recipient_name']) ? $data['recipient_name'] : null;
        $dto->recipientPhone = isset($data['recipient_phone']) && \is_string($data['recipient_phone']) ? $data['recipient_phone'] : null;
        $dto->notes = isset($data['notes']) && \is_string($data['notes']) ? $data['notes'] : null;
        $dto->description = isset($data['description']) && \is_string($data['description']) ? $data['description'] : null;
        $dto->latitude = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $dto->longitude = isset($data['longitude']) ? (float) $data['longitude'] : null;
        $dto->totalWeightKg = isset($data['total_weight_kg']) ? (float) $data['total_weight_kg'] : null;
        $dto->totalVolumeM3 = isset($data['total_volume_m3']) ? (float) $data['total_volume_m3'] : null;
        $dto->totalParcels = isset($data['total_parcels']) ? (int) $data['total_parcels'] : null;

        return $dto;
    }
}
