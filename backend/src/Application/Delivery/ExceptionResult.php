<?php

declare(strict_types=1);

namespace App\Application\Delivery;

final readonly class ExceptionResult
{
    public function __construct(
        public bool $idempotent,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => true,
            'idempotent' => $this->idempotent,
        ];
    }
}
