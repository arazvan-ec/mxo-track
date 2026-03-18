<?php

declare(strict_types=1);

namespace App\Domain\MapView\Model;

final readonly class MapUpdate
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public MapUpdateType $type,
        public string $routePublicId,
        public array $data,
        public \DateTimeImmutable $occurredAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'routePublicId' => $this->routePublicId,
            'data' => $this->data,
            'occurredAt' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
