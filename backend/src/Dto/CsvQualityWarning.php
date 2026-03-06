<?php

declare(strict_types=1);

namespace App\Dto;

final class CsvQualityWarning
{
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_INFO = 'info';

    public function __construct(
        public readonly int $rowNumber,
        public readonly string $field,
        public readonly string $message,
        public readonly string $severity,
    ) {
    }

    public function toArray(): array
    {
        return [
            'row_number' => $this->rowNumber,
            'field' => $this->field,
            'message' => $this->message,
            'severity' => $this->severity,
        ];
    }
}
