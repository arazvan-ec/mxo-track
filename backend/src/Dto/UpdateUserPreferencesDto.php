<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateUserPreferencesDto
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['expanded', 'collapsed'])]
    public string $widgetDefaultMode = '';

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->widgetDefaultMode = \is_string($data['widget_default_mode'] ?? null) ? $data['widget_default_mode'] : '';

        return $dto;
    }
}
