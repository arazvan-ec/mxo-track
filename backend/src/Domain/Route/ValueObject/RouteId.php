<?php

declare(strict_types=1);

namespace App\Domain\Route\ValueObject;

final readonly class RouteId implements \Stringable
{
    public function __construct(
        private string $value,
    ) {
        if ($value === '') {
            throw new \InvalidArgumentException('RouteId cannot be empty.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
