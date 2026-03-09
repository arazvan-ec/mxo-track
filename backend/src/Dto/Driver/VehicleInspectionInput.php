<?php

declare(strict_types=1);

namespace App\Dto\Driver;

use Symfony\Component\Validator\Constraints as Assert;

final class VehicleInspectionInput
{
    /**
     * @var array<int, array<string, mixed>>
     */
    #[Assert\NotBlank(message: 'La lista de elementos de inspección es obligatoria.')]
    #[Assert\All([
        new Assert\Collection(
            fields: [
                'name' => [
                    new Assert\NotBlank(message: 'El nombre del elemento es obligatorio.'),
                    new Assert\Type('string'),
                ],
                'checked' => [
                    new Assert\NotNull(message: 'El estado de verificación es obligatorio.'),
                    new Assert\Type('bool'),
                ],
                'note' => new Assert\Optional([
                    new Assert\Type('string'),
                ]),
            ],
            allowExtraFields: false,
        ),
    ])]
    public array $items = [];

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->items = is_array($data['items'] ?? null) ? $data['items'] : [];

        return $dto;
    }
}
