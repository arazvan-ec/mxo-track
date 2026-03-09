<?php

declare(strict_types=1);

namespace App\Dto\Api;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateWebhookRequest
{
    #[Assert\NotBlank(message: 'URL is required.')]
    #[Assert\Url(message: 'URL must be a valid HTTPS endpoint.')]
    public string $url = '';

    /**
     * @var string[]
     */
    #[Assert\NotBlank(message: 'At least one event type is required.')]
    #[Assert\All([
        new Assert\Choice(
            choices: ['shipment.created', 'shipment.delivered', 'shipment.exception', 'route.started', 'route.completed'],
            message: 'Invalid event type "{{ value }}".',
        ),
    ])]
    public array $events = [];

    #[Assert\Length(min: 16, max: 128, minMessage: 'Secret must be at least 16 characters.')]
    public ?string $secret = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->url = \is_string($data['url'] ?? null) ? $data['url'] : '';
        $dto->events = isset($data['events']) && \is_array($data['events'])
            ? array_values(array_filter($data['events'], 'is_string'))
            : [];
        $dto->secret = isset($data['secret']) && \is_string($data['secret']) ? $data['secret'] : null;

        return $dto;
    }
}
