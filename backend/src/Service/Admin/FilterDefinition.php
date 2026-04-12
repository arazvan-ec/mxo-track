<?php

declare(strict_types=1);

namespace App\Service\Admin;

final class FilterDefinition
{
    private function __construct(
        public readonly string $type,
        public readonly string $field,
        public readonly string $paramName,
        public readonly mixed $value,
        public readonly ?string $enumClass = null,
        public readonly ?string $countJoin = null,
        public readonly ?string $countJoinAlias = null,
    ) {}

    public static function boolean(string $field, string $paramName, string $rawValue): self
    {
        if ($rawValue === '') {
            return new self('skip', $field, $paramName, null);
        }

        return new self('boolean', $field, $paramName, $rawValue === 'true');
    }

    public static function like(string $field, string $paramName, string $rawValue): self
    {
        $trimmed = trim($rawValue);
        if ($trimmed === '') {
            return new self('skip', $field, $paramName, null);
        }

        return new self('like', $field, $paramName, '%' . mb_strtolower($trimmed) . '%');
    }

    public static function enum(string $field, string $paramName, string $rawValue, string $enumClass): self
    {
        if ($rawValue === '') {
            return new self('skip', $field, $paramName, null);
        }

        $enum = $enumClass::tryFrom($rawValue);
        if ($enum === null) {
            // Try int-backed enums
            $enum = $enumClass::tryFrom((int) $rawValue);
        }
        if ($enum === null) {
            return new self('skip', $field, $paramName, null);
        }

        return new self('enum', $field, $paramName, $enum, $enumClass);
    }

    public static function dateFrom(string $field, string $paramName, string $rawValue): self
    {
        if ($rawValue === '') {
            return new self('skip', $field, $paramName, null);
        }

        try {
            $date = new \DateTimeImmutable($rawValue . ' 00:00:00');
        } catch (\Exception) {
            return new self('skip', $field, $paramName, null);
        }

        return new self('date_gte', $field, $paramName, $date);
    }

    public static function dateTo(string $field, string $paramName, string $rawValue): self
    {
        if ($rawValue === '') {
            return new self('skip', $field, $paramName, null);
        }

        try {
            $date = new \DateTimeImmutable($rawValue . ' 23:59:59');
        } catch (\Exception) {
            return new self('skip', $field, $paramName, null);
        }

        return new self('date_lte', $field, $paramName, $date);
    }

    public static function entity(string $field, string $paramName, mixed $value): self
    {
        if ($value === null || $value === '') {
            return new self('skip', $field, $paramName, null);
        }

        return new self('entity', $field, $paramName, $value);
    }

    public function withCountJoin(string $join, string $alias): self
    {
        return new self(
            $this->type,
            $this->field,
            $this->paramName,
            $this->value,
            $this->enumClass,
            $join,
            $alias,
        );
    }

    public function isActive(): bool
    {
        return $this->type !== 'skip';
    }
}
