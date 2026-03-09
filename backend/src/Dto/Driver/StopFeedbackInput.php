<?php

declare(strict_types=1);

namespace App\Dto\Driver;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class StopFeedbackInput
{
    #[Assert\Range(min: -90, max: 90)]
    public ?float $correctedLat = null;

    #[Assert\Range(min: -180, max: 180)]
    public ?float $correctedLng = null;

    #[Assert\Length(max: 4000)]
    public ?string $accessNotes = null;

    #[Assert\Positive]
    public ?int $actualServiceTimeSeconds = null;

    #[Assert\Length(max: 4000)]
    public ?string $comment = null;

    #[Assert\Callback]
    public function validateAtLeastOneField(ExecutionContextInterface $context): void
    {
        if (
            $this->correctedLat === null
            && $this->correctedLng === null
            && $this->accessNotes === null
            && $this->actualServiceTimeSeconds === null
            && $this->comment === null
        ) {
            $context->buildViolation('Al menos un campo debe tener valor.')
                ->atPath('comment')
                ->addViolation();
        }
    }

    public static function fromArray(array $payload): self
    {
        $dto = new self();
        $dto->correctedLat = isset($payload['corrected_lat']) ? (float) $payload['corrected_lat'] : null;
        $dto->correctedLng = isset($payload['corrected_lng']) ? (float) $payload['corrected_lng'] : null;
        $dto->accessNotes = isset($payload['access_notes']) ? (string) $payload['access_notes'] : null;
        $dto->actualServiceTimeSeconds = isset($payload['actual_service_time_seconds']) ? (int) $payload['actual_service_time_seconds'] : null;
        $dto->comment = isset($payload['comment']) ? (string) $payload['comment'] : null;

        return $dto;
    }
}
