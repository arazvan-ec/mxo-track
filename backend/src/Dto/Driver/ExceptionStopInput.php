<?php

declare(strict_types=1);

namespace App\Dto\Driver;

use Symfony\Component\Validator\Constraints as Assert;

final class ExceptionStopInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $clientActionId = '';

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['ABSENT', 'WRONG_ADDRESS', 'REFUSED', 'DAMAGED', 'OTHER'])]
    public string $reason = 'OTHER';

    #[Assert\Length(max: 4000)]
    public string $comment = '';

    #[Assert\Ulid]
    public ?string $shipmentPublicId = null;

    public static function fromArray(array $payload): self
    {
        $dto = new self();
        $dto->clientActionId = (string) ($payload['client_action_id'] ?? '');
        $dto->reason = (string) ($payload['reason'] ?? 'OTHER');
        $dto->comment = (string) ($payload['comment'] ?? '');
        $dto->shipmentPublicId = isset($payload['shipment_public_id']) ? (string) $payload['shipment_public_id'] : null;

        return $dto;
    }
}
