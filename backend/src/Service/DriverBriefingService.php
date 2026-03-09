<?php

declare(strict_types=1);

namespace App\Service;

use App\Ai\LlmClientInterface;
use App\Ai\LlmRequest;
use App\Dto\DriverBriefing;
use App\Entity\AddressRisk;
use App\Entity\Route;
use App\Entity\RouteStop;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Generates an AI-powered briefing for drivers before starting a route.
 */
final class DriverBriefingService
{
    public function __construct(
        private readonly LlmClientInterface $llmClient,
        private readonly RateLimitedApiClient $rateLimiter,
        private readonly AddressRiskService $addressRisk,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function generateBriefing(Route $route): DriverBriefing
    {
        // 1. Gather route data
        $stops = $this->loadStops($route);
        $deliveryStops = array_values(array_filter($stops, static fn (RouteStop $s): bool => !$s->isOrigin()));
        $totalStops = count($deliveryStops);

        // 2. Check high-risk addresses via AddressRiskService
        $highRiskStops = 0;
        $highRiskDetails = [];
        $stopNotes = [];
        $warnings = [];

        foreach ($deliveryStops as $stop) {
            $risk = $this->addressRisk->checkAddress($stop->getAddress());
            if ($risk instanceof AddressRisk && $risk->isHighRisk()) {
                $highRiskStops++;
                $pct = (int) round($risk->getExceptionRate() * 100);
                $detail = sprintf('%d%% excepciones en %d entregas', $pct, $risk->getTotalDeliveries());
                $highRiskDetails[] = sprintf(
                    'Parada #%d (%s): %s',
                    $stop->getSequence(),
                    $stop->getAddress(),
                    $detail,
                );
                $warnings[] = sprintf('Alto riesgo: %s (%s)', $stop->getAddress(), $detail);
            }

            if ($stop->getNotes() !== null && $stop->getNotes() !== '') {
                $stopNotes[] = sprintf(
                    'Parada #%d (%s): %s',
                    $stop->getSequence(),
                    $stop->getAddress(),
                    $stop->getNotes(),
                );
            }
        }

        // 3. Estimated duration from route start/end times or heuristic
        $estimatedDurationMinutes = $this->estimateDuration($route, $deliveryStops);

        // 4. Capacity utilization (vehicle has no capacity fields yet)
        $capacityUtilizationPercent = null;

        // 5. Build context for Claude prompt
        $contextData = $this->buildContextData(
            $route,
            $totalStops,
            $highRiskStops,
            $highRiskDetails,
            $stopNotes,
            $estimatedDurationMinutes,
            $capacityUtilizationPercent,
            $deliveryStops,
        );

        // 6. Call Claude API (with rate limiting and fallback)
        $summary = $this->generateAiSummary($contextData);

        // 7. If AI failed, generate a basic summary
        if ($summary === null) {
            $summary = $this->buildFallbackSummary(
                $route,
                $totalStops,
                $highRiskStops,
                $estimatedDurationMinutes,
                $highRiskDetails,
            );
        }

        return new DriverBriefing(
            summary: $summary,
            totalStops: $totalStops,
            highRiskStops: $highRiskStops,
            estimatedDurationMinutes: $estimatedDurationMinutes,
            capacityUtilizationPercent: $capacityUtilizationPercent,
            warnings: $warnings,
            generatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * @return list<RouteStop>
     */
    private function loadStops(Route $route): array
    {
        return $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $route)
            ->orderBy('s.sequence', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<RouteStop> $deliveryStops
     */
    private function estimateDuration(Route $route, array $deliveryStops): ?int
    {
        if ($route->getStartAt() !== null && $route->getEndAt() !== null) {
            $diff = $route->getEndAt()->getTimestamp() - $route->getStartAt()->getTimestamp();

            return max(0, (int) round($diff / 60));
        }

        $stopCount = count($deliveryStops);
        if ($stopCount === 0) {
            return null;
        }

        // Heuristic: ~15 min per stop (travel + delivery)
        return $stopCount * 15;
    }

    /**
     * @param list<RouteStop> $deliveryStops
     * @return array<string, mixed>
     */
    private function buildContextData(
        Route $route,
        int $totalStops,
        int $highRiskStops,
        array $highRiskDetails,
        array $stopNotes,
        ?int $estimatedDurationMinutes,
        ?float $capacityUtilizationPercent,
        array $deliveryStops,
    ): array {
        $addresses = array_map(
            static fn (RouteStop $s): string => sprintf('#%d: %s', $s->getSequence(), $s->getAddress()),
            $deliveryStops,
        );

        return [
            'route_name' => $route->getName(),
            'total_stops' => $totalStops,
            'addresses' => $addresses,
            'high_risk_stops' => $highRiskStops,
            'high_risk_details' => $highRiskDetails,
            'stop_notes' => $stopNotes,
            'estimated_duration_minutes' => $estimatedDurationMinutes,
            'capacity_utilization_percent' => $capacityUtilizationPercent,
            'vehicle_name' => $route->getVehicle()?->getName(),
            'driver_name' => $route->getDriver()?->getName(),
        ];
    }

    /**
     * @param array<string, mixed> $contextData
     */
    private function generateAiSummary(array $contextData): ?string
    {
        $systemPrompt = <<<'PROMPT'
Eres un asistente de logística para conductores de reparto. Genera un briefing breve y práctico en español para que el conductor sepa qué esperar en su ruta. El briefing debe tener 3-5 frases concisas. Incluye:
- Número de paradas
- Direcciones de alto riesgo (si las hay) con el motivo
- Notas importantes de las paradas
- Estimación de tiempo
- Utilización de capacidad del vehículo (si se conoce)
No uses formato markdown. Escribe texto plano directo.
PROMPT;

        $userMessage = $this->buildUserMessage($contextData);

        try {
            /** @var string|null $text */
            $text = $this->rateLimiter->call(
                fn (): string => $this->llmClient->complete(
                    new LlmRequest($systemPrompt, $userMessage, maxTokens: 512),
                )->content,
                maxPerMinute: 30,
                clientName: 'claude-briefing',
            );

            return ($text !== null && $text !== '') ? $text : null;
        } catch (\Throwable $e) {
            $this->logger->warning('DriverBriefingService: Claude API call failed.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $contextData
     */
    private function buildUserMessage(array $contextData): string
    {
        $durationText = $contextData['estimated_duration_minutes'] !== null
            ? sprintf('%dh%02dm', intdiv($contextData['estimated_duration_minutes'], 60), $contextData['estimated_duration_minutes'] % 60)
            : 'desconocida';

        $parts = [
            sprintf('Ruta: %s', $contextData['route_name']),
            sprintf('Total paradas: %d', $contextData['total_stops']),
            sprintf('Duración estimada: %s', $durationText),
        ];

        if ($contextData['high_risk_stops'] > 0) {
            $parts[] = sprintf('Paradas de alto riesgo (%d):', $contextData['high_risk_stops']);
            foreach ($contextData['high_risk_details'] as $detail) {
                $parts[] = '  - ' . $detail;
            }
        }

        if (count($contextData['stop_notes']) > 0) {
            $parts[] = 'Notas en paradas:';
            foreach ($contextData['stop_notes'] as $note) {
                $parts[] = '  - ' . $note;
            }
        }

        if ($contextData['capacity_utilization_percent'] !== null) {
            $parts[] = sprintf('Utilización del vehículo: %.0f%%', $contextData['capacity_utilization_percent']);
        }

        if ($contextData['vehicle_name'] !== null) {
            $parts[] = sprintf('Vehículo: %s', $contextData['vehicle_name']);
        }

        return implode("\n", $parts);
    }

    private function buildFallbackSummary(
        Route $route,
        int $totalStops,
        int $highRiskStops,
        ?int $estimatedDurationMinutes,
        array $highRiskDetails,
    ): string {
        $parts = [];

        $durationText = $estimatedDurationMinutes !== null
            ? sprintf('%dh%02dm', intdiv($estimatedDurationMinutes, 60), $estimatedDurationMinutes % 60)
            : 'no disponible';

        $parts[] = sprintf(
            'Ruta "%s" con %d parada%s, duración estimada %s.',
            $route->getName(),
            $totalStops,
            $totalStops !== 1 ? 's' : '',
            $durationText,
        );

        if ($highRiskStops > 0) {
            $parts[] = sprintf(
                '%d parada%s de alto riesgo: %s.',
                $highRiskStops,
                $highRiskStops !== 1 ? 's' : '',
                implode('; ', $highRiskDetails),
            );
        }

        if ($route->getVehicle() !== null) {
            $parts[] = sprintf('Vehículo asignado: %s.', $route->getVehicle()->getName());
        }

        return implode(' ', $parts);
    }
}
