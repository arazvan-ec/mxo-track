<?php

declare(strict_types=1);

namespace App\Dto\Driver;

use Symfony\Component\Validator\Constraints as Assert;

final class DeliverStopInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $clientActionId = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $signedByName = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 6, max: 512)]
    public string $recipientIdEncoded = '';

    #[Assert\Type('bool')]
    public bool $confirmedByDriver = false;

    #[Assert\Ulid]
    public ?string $shipmentPublicId = null;

    public static function fromArray(array $payload): self
    {
        $dto = new self();
        $dto->clientActionId = (string) ($payload['client_action_id'] ?? '');
        $dto->signedByName = (string) ($payload['signed_by_name'] ?? '');
        $dto->recipientIdEncoded = (string) ($payload['recipient_id_encoded'] ?? '');
        $dto->confirmedByDriver = (bool) ($payload['confirmed_by_driver'] ?? false);
        $dto->shipmentPublicId = isset($payload['shipment_public_id']) ? (string) $payload['shipment_public_id'] : null;

        return $dto;
    }
}
