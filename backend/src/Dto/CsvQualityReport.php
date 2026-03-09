<?php

declare(strict_types=1);

namespace App\Dto;

final class CsvQualityReport
{
    /** @param list<CsvQualityWarning> $warnings */
    public function __construct(
        public readonly int $score,
        public readonly array $warnings,
    ) {
    }

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'warnings' => array_map(
                static fn (CsvQualityWarning $w) => $w->toArray(),
                $this->warnings,
            ),
        ];
    }
}
