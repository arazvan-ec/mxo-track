<?php

declare(strict_types=1);

namespace App\Dto;

use DateTimeImmutable;

final readonly class DriverBriefing
{
    /**
     * @param string   $summary                     The main briefing text (3-5 sentences)
     * @param int      $totalStops                  Number of delivery stops (excluding origin)
     * @param int      $highRiskStops               Count of stops with high-risk addresses
     * @param ?int     $estimatedDurationMinutes     Estimated total route duration in minutes
     * @param ?float   $capacityUtilizationPercent   Vehicle capacity utilization percentage
     * @param string[] $warnings                     List of specific warning strings
     */
    public function __construct(
        public string $summary,
        public int $totalStops,
        public int $highRiskStops,
        public ?int $estimatedDurationMinutes,
        public ?float $capacityUtilizationPercent,
        public array $warnings,
        public DateTimeImmutable $generatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'total_stops' => $this->totalStops,
            'high_risk_stops' => $this->highRiskStops,
            'estimated_duration_minutes' => $this->estimatedDurationMinutes,
            'capacity_utilization_percent' => $this->capacityUtilizationPercent,
            'warnings' => $this->warnings,
            'generated_at' => $this->generatedAt->format(\DATE_ATOM),
        ];
    }
}
