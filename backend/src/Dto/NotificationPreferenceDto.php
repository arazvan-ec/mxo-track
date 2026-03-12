<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class NotificationPreferenceDto
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['reminder', 'presence_check', 'delivered', 'delivery_exception', 'eta_change', 'out_for_delivery'])]
    public string $triggerType = '';

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['sms', 'whatsapp'])]
    public string $channel = '';

    public bool $enabled = true;

    #[Assert\Length(max: 500)]
    public ?string $messageTemplate = null;

    public array $timingConfig = [];

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->triggerType = \is_string($data['trigger_type'] ?? null) ? $data['trigger_type'] : '';
        $dto->channel = \is_string($data['channel'] ?? null) ? $data['channel'] : '';
        $dto->enabled = (bool) ($data['enabled'] ?? true);
        $dto->messageTemplate = isset($data['message_template']) && \is_string($data['message_template']) ? $data['message_template'] : null;
        $dto->timingConfig = isset($data['timing_config']) && \is_array($data['timing_config']) ? $data['timing_config'] : [];

        return $dto;
    }
}
