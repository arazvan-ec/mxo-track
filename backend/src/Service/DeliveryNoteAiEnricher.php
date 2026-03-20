<?php

declare(strict_types=1);

namespace App\Service;

use App\Ai\LlmClientInterface;
use App\Ai\LlmRequest;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Domain\Shipment\Model\ShipmentEvent;
use App\Enum\ShipmentEventType;
use App\Repository\DriverFeedbackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class DeliveryNoteAiEnricher
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Eres un asistente de logística. Genera una nota concisa (máximo 200 caracteres) en español para un repartidor que va a entregar en una dirección.

La nota debe incluir información útil basada en el historial:
- Instrucciones de acceso (portería, timbre, piso)
- Problemas conocidos (horarios difíciles, ausencias frecuentes)
- Historial de excepciones recientes
- Si hay coordenadas corregidas disponibles, mencionarlo

Responde SOLO con la nota, sin comillas ni explicaciones adicionales.
PROMPT;

    public function __construct(
        private readonly LlmClientInterface $llmClient,
        private readonly RateLimitedApiClient $rateLimiter,
        private readonly EntityManagerInterface $em,
        private readonly DriverFeedbackRepository $feedbackRepo,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Enrich a single stop with AI-generated delivery notes based on historical data.
     */
    public function enrichStop(RouteStop $stop): ?string
    {
        $address = $stop->getAddress();
        $context = $this->gatherHistoricalData($address);

        if ($context === '') {
            return null;
        }

        $userMessage = sprintf(
            "Dirección de entrega: %s\n\nHistorial conocido:\n%s",
            $address,
            $context,
        );

        $note = $this->rateLimiter->call(
            fn (): string => $this->llmClient->complete(
                new LlmRequest(self::SYSTEM_PROMPT, $userMessage, maxTokens: 150),
            )->content,
        );

        if ($note === '') {
            return null;
        }

        if (mb_strlen($note) > 200) {
            $note = mb_substr($note, 0, 197) . '...';
        }

        return $note;
    }

    /**
     * Enrich all non-origin stops in a route with AI notes.
     *
     * @return int Number of stops enriched
     */
    public function enrichRoute(Route $route): int
    {
        $stops = $this->em->getRepository(RouteStop::class)->findBy(
            ['route' => $route],
            ['sequence' => 'ASC'],
        );

        $enriched = 0;

        foreach ($stops as $stop) {
            if ($stop->isOrigin()) {
                continue;
            }

            try {
                $note = $this->enrichStop($stop);
            } catch (\Throwable $e) {
                $this->logger->warning('DeliveryNoteAiEnricher: failed to enrich stop {stop}: {error}', [
                    'stop' => $stop->getAddress(),
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($note !== null) {
                $stop->setAiNotes($note);
                $enriched++;
            }
        }

        if ($enriched > 0) {
            $this->em->flush();
        }

        $this->logger->info('DeliveryNoteAiEnricher: enriched {count} stops for route {route}', [
            'count' => $enriched,
            'route' => $route->getName(),
        ]);

        return $enriched;
    }

    private function gatherHistoricalData(string $address): string
    {
        $parts = [];

        // 1. Past ShipmentEvents at same address (exceptions with notes)
        $exceptionEvents = $this->findExceptionEventsAtAddress($address);
        if ($exceptionEvents !== []) {
            $parts[] = "Excepciones previas en esta dirección:";
            foreach ($exceptionEvents as $event) {
                $payload = $event->getPayload();
                $notes = $payload['notes'] ?? $payload['exception_notes'] ?? '';
                $date = $event->getCreatedAt()->format('d/m/Y');
                $parts[] = sprintf('- %s: %s', $date, $notes);
            }
        }

        // 2. DriverFeedback entries for same address (via stop relation)
        $feedbacks = $this->feedbackRepo->findByAddress($address);
        if ($feedbacks !== []) {
            $parts[] = "Feedback de conductores:";
            foreach ($feedbacks as $feedback) {
                if ($feedback->getAccessNotes() !== null) {
                    $parts[] = sprintf('- Notas acceso: %s', $feedback->getAccessNotes());
                }
                if ($feedback->getCorrectedLat() !== null && $feedback->getCorrectedLng() !== null) {
                    $parts[] = '- Coordenadas corregidas disponibles';
                }
                if ($feedback->getComment() !== null) {
                    $parts[] = sprintf('- Comentario: %s', $feedback->getComment());
                }
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Find recent EXCEPTION ShipmentEvents for shipments at the given address.
     *
     * @return ShipmentEvent[]
     */
    private function findExceptionEventsAtAddress(string $address): array
    {
        return $this->em->createQueryBuilder()
            ->select('se')
            ->from(ShipmentEvent::class, 'se')
            ->join('se.shipment', 's')
            ->where('s.address = :address')
            ->andWhere('se.eventType = :eventType')
            ->setParameter('address', $address)
            ->setParameter('eventType', ShipmentEventType::EXCEPTION)
            ->orderBy('se.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }
}
