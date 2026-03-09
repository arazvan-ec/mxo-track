<?php

declare(strict_types=1);

namespace App\Application\Route;

final readonly class OptimizationResult
{
    /**
     * @param array<array{publicId: string, address: string, currentSequence: int, newSequence: int, isOrigin: bool}> $stops
     */
    public function __construct(
        public bool $applied,
        public float $distanceBefore,
        public float $distanceAfter,
        public float $improvementPercent,
        public ?int $durationMinutes,
        public array $stops = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'ok' => true,
            'applied' => $this->applied,
            'distanceBefore' => round($this->distanceBefore, 2),
            'distanceAfter' => round($this->distanceAfter, 2),
            'improvement' => round($this->improvementPercent, 1),
        ];

        if ($this->durationMinutes !== null) {
            $data['estimatedDurationMinutes'] = $this->durationMinutes;
        }

        if (!$this->applied && $this->stops !== []) {
            $data['stops'] = $this->stops;
        }

        return $data;
    }
}
