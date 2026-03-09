<?php

declare(strict_types=1);

namespace App\Service;

use App\Ai\LlmClientInterface;
use App\Ai\LlmRequest;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Enum\RouteStopStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Generates AI-powered post-route analysis comparing planned vs actual performance.
 *
 * Called when a route finishes (status -> DONE).
 */
final class PostRouteAnalyzer
{
    public function __construct(
        private readonly LlmClientInterface $llmClient,
        private readonly RateLimitedApiClient $rateLimitedApiClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Analyze a completed route and return a structured analysis.
     *
     * @return array{summary: string, planned_vs_actual: string, insights: list<string>, recommendations: list<string>}
     */
    public function analyze(Route $route): array
    {
        $stops = $this->entityManager->getRepository(RouteStop::class)->findBy(
            ['route' => $route],
            ['sequence' => 'ASC'],
        );

        $stats = $this->gatherStats($route, $stops);

        try {
            return $this->analyzeWithAi($stats);
        } catch (\Throwable $e) {
            $this->logger?->warning('Post-route AI analysis failed for route {id}: {error}', [
                'id' => $route->getPublicIdString(),
                'error' => $e->getMessage(),
            ]);

            // Return basic stats without AI insights
            return $this->buildFallbackAnalysis($stats);
        }
    }

    /**
     * Gather statistics from the route and its stops.
     *
     * @param list<RouteStop> $stops
     * @return array<string, mixed>
     */
    private function gatherStats(Route $route, array $stops): array
    {
        $totalStops = 0;
        $deliveredCount = 0;
        $exceptionCount = 0;
        $pendingCount = 0;
        $skippedCount = 0;
        /** @var array<string, int> $exceptionCodes */
        $exceptionCodes = [];
        $stopTimings = [];

        foreach ($stops as $stop) {
            if ($stop->isOrigin()) {
                continue;
            }

            $totalStops++;

            match ($stop->getStatus()) {
                RouteStopStatus::DELIVERED => $deliveredCount++,
                RouteStopStatus::EXCEPTION => $exceptionCount++,
                RouteStopStatus::PENDING => $pendingCount++,
                RouteStopStatus::SKIPPED => $skippedCount++,
            };

            if ($stop->getStatus() === RouteStopStatus::EXCEPTION && $stop->getExceptionCode() !== null) {
                $code = $stop->getExceptionCode()->value;
                $exceptionCodes[$code] = ($exceptionCodes[$code] ?? 0) + 1;
            }

            $stopTimings[] = [
                'sequence' => $stop->getSequence(),
                'address' => $stop->getAddress(),
                'status' => $stop->getStatus()->value,
                'delivered_at' => $stop->getDeliveredAt()?->format('H:i:s'),
                'exception_code' => $stop->getExceptionCode()?->value,
            ];
        }

        // Calculate actual duration
        $actualDurationMinutes = null;
        if ($route->getStartAt() !== null && $route->getEndAt() !== null) {
            $diff = $route->getEndAt()->getTimestamp() - $route->getStartAt()->getTimestamp();
            $actualDurationMinutes = (int) round($diff / 60);
        }

        return [
            'route_name' => $route->getName(),
            'status' => $route->getStatus()->value,
            'total_stops' => $totalStops,
            'delivered' => $deliveredCount,
            'exceptions' => $exceptionCount,
            'pending' => $pendingCount,
            'skipped' => $skippedCount,
            'exception_codes' => $exceptionCodes,
            'estimated_duration_minutes' => $route->getEstimatedDurationMinutes(),
            'actual_duration_minutes' => $actualDurationMinutes,
            'total_distance_km' => $route->getTotalDistanceKm(),
            'start_at' => $route->getStartAt()?->format('Y-m-d H:i:s'),
            'end_at' => $route->getEndAt()?->format('Y-m-d H:i:s'),
            'stop_timings' => $stopTimings,
            'delivery_rate' => $totalStops > 0 ? round(($deliveredCount / $totalStops) * 100, 1) : 0,
        ];
    }

    /**
     * Call Claude AI to generate structured analysis.
     *
     * @param array<string, mixed> $stats
     * @return array{summary: string, planned_vs_actual: string, insights: list<string>, recommendations: list<string>}
     */
    private function analyzeWithAi(array $stats): array
    {
        $systemPrompt = <<<'PROMPT'
Eres un analista de logística. Analiza los datos de rendimiento de una ruta de reparto completada y genera un informe estructurado en español.

Responde SIEMPRE en formato JSON válido con exactamente esta estructura:
{
  "summary": "Resumen general del rendimiento de la ruta (2-3 frases)",
  "planned_vs_actual": "Comparación entre la duración estimada y la real, y análisis de desviaciones",
  "insights": ["Observación 1", "Observación 2", ...],
  "recommendations": ["Recomendación 1", "Recomendación 2", ...]
}

Directrices:
- El resumen debe ser conciso y dar una visión general del rendimiento
- En planned_vs_actual, compara tiempos estimados vs reales si están disponibles
- Los insights deben destacar patrones (muchas excepciones de un tipo, entregas agrupadas, etc.)
- Las recomendaciones deben ser accionables y específicas
- Si la tasa de entrega es < 80%, destacarlo como problema
- Si hay excepciones frecuentes de un código, sugerir acciones correctivas
- Usa español profesional pero directo
PROMPT;

        $userMessage = sprintf(
            "Datos de la ruta completada:\n%s",
            json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        /** @var string $response */
        $response = $this->rateLimitedApiClient->call(
            fn () => $this->llmClient->complete(new LlmRequest($systemPrompt, $userMessage, maxTokens: 1500))->content,
        );

        return $this->parseAnalysisResponse($response, $stats);
    }

    /**
     * Parse the AI response into the expected structure.
     *
     * @param array<string, mixed> $stats
     * @return array{summary: string, planned_vs_actual: string, insights: list<string>, recommendations: list<string>}
     */
    private function parseAnalysisResponse(string $response, array $stats): array
    {
        // Extract JSON from response (Claude may wrap it in markdown code blocks)
        $json = $response;
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $response, $matches)) {
            $json = $matches[1];
        }

        $data = json_decode(trim($json), true);

        if (!is_array($data)) {
            $this->logger?->warning('Could not parse AI analysis response as JSON, using fallback');
            return $this->buildFallbackAnalysis($stats);
        }

        return [
            'summary' => is_string($data['summary'] ?? null) ? $data['summary'] : '',
            'planned_vs_actual' => is_string($data['planned_vs_actual'] ?? null) ? $data['planned_vs_actual'] : '',
            'insights' => is_array($data['insights'] ?? null)
                ? array_values(array_filter($data['insights'], 'is_string'))
                : [],
            'recommendations' => is_array($data['recommendations'] ?? null)
                ? array_values(array_filter($data['recommendations'], 'is_string'))
                : [],
        ];
    }

    /**
     * Build a basic analysis without AI when the API call fails.
     *
     * @param array<string, mixed> $stats
     * @return array{summary: string, planned_vs_actual: string, insights: list<string>, recommendations: list<string>}
     */
    private function buildFallbackAnalysis(array $stats): array
    {
        $totalStops = (int) ($stats['total_stops'] ?? 0);
        $delivered = (int) ($stats['delivered'] ?? 0);
        $exceptions = (int) ($stats['exceptions'] ?? 0);
        $deliveryRate = (float) ($stats['delivery_rate'] ?? 0);

        $summary = sprintf(
            'Ruta "%s" completada con %d de %d entregas exitosas (%.1f%% tasa de entrega).',
            $stats['route_name'] ?? 'Sin nombre',
            $delivered,
            $totalStops,
            $deliveryRate,
        );

        if ($exceptions > 0) {
            $summary .= sprintf(' Se registraron %d excepciones.', $exceptions);
        }

        $plannedVsActual = 'Sin datos suficientes para comparar planificado vs real.';
        $estimated = $stats['estimated_duration_minutes'] ?? null;
        $actual = $stats['actual_duration_minutes'] ?? null;
        if ($estimated !== null && $actual !== null) {
            $diff = $actual - $estimated;
            $plannedVsActual = sprintf(
                'Duración estimada: %d min, real: %d min (%s%d min).',
                $estimated,
                $actual,
                $diff >= 0 ? '+' : '',
                $diff,
            );
        }

        $insights = [];
        if ($deliveryRate < 80) {
            $insights[] = sprintf('Tasa de entrega por debajo del 80%% (%.1f%%).', $deliveryRate);
        }
        if ($exceptions > 0) {
            foreach ($stats['exception_codes'] ?? [] as $code => $count) {
                $insights[] = sprintf('Excepción %s: %d ocurrencia(s).', $code, $count);
            }
        }

        return [
            'summary' => $summary,
            'planned_vs_actual' => $plannedVsActual,
            'insights' => $insights,
            'recommendations' => [],
        ];
    }
}
