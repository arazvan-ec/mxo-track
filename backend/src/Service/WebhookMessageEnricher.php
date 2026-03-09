<?php

declare(strict_types=1);

namespace App\Service;

use App\Ai\LlmClientInterface;
use App\Ai\LlmRequest;
use App\Enum\ShipmentEventType;
use Psr\Log\LoggerInterface;

final class WebhookMessageEnricher
{
    private const int RATE_LIMIT_PER_MINUTE = 30;

    public function __construct(
        private readonly LlmClientInterface $llmClient,
        private readonly RateLimitedApiClient $rateLimitedApiClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Enrich a webhook payload with a human-readable customer_message in Spanish.
     *
     * For simple events (DELIVERED, OUT_FOR_DELIVERY, etc.) uses template strings.
     * For EXCEPTION events, calls Claude to generate a friendlier message.
     *
     * @return array The original payload with an added 'customer_message' key
     */
    public function enrichPayload(string $eventType, array $payload): array
    {
        $message = match ($eventType) {
            ShipmentEventType::OUT_FOR_DELIVERY->value => $this->buildOutForDeliveryMessage($payload),
            ShipmentEventType::DELIVERED->value => $this->buildDeliveredMessage($payload),
            ShipmentEventType::EXCEPTION->value => $this->buildExceptionMessage($payload),
            ShipmentEventType::CREATED->value => 'Tu envío ha sido registrado y está siendo preparado.',
            ShipmentEventType::PICKED_UP->value => 'Tu paquete ha sido recogido y está en camino al centro de distribución.',
            ShipmentEventType::IN_HUB->value => 'Tu paquete ha llegado al centro de distribución.',
            ShipmentEventType::IN_TRANSIT->value => 'Tu paquete está en tránsito hacia tu zona.',
            default => 'El estado de tu envío ha sido actualizado.',
        };

        $payload['customer_message'] = $message;

        return $payload;
    }

    /**
     * Generate a customer-friendly exception message using Claude.
     *
     * Rate limited to 30 requests per minute.
     */
    public function generateExceptionMessage(string $exceptionCode, ?string $notes): string
    {
        $userMessage = sprintf(
            "Código de excepción: %s\nNotas del conductor: %s",
            $exceptionCode,
            $notes ?? 'Sin notas adicionales',
        );

        $systemPrompt = <<<'PROMPT'
Eres un asistente de comunicación para una empresa de logística en España.
Genera un mensaje breve y amable en español para el cliente explicando por qué no se pudo entregar su paquete.
El mensaje debe ser empático, profesional y no más de 2 frases.
No incluyas códigos internos ni tecnicismos.
Siempre menciona que se programará un nuevo intento de entrega.
Responde SOLO con el mensaje, sin comillas ni formato adicional.
PROMPT;

        /** @var string $text */
        $text = $this->rateLimitedApiClient->call(
            fn (): string => $this->llmClient->complete(
                new LlmRequest($systemPrompt, $userMessage, temperature: 0.4, maxTokens: 256),
            )->content,
            self::RATE_LIMIT_PER_MINUTE,
            'webhook_enrichment',
        );

        if ($text === '') {
            $this->logger->warning('Claude returned empty text for exception message, using fallback', [
                'exception_code' => $exceptionCode,
            ]);

            return $this->fallbackExceptionMessage($exceptionCode, $notes);
        }

        return $text;
    }

    private function buildOutForDeliveryMessage(array $payload): string
    {
        $eta = $payload['eta'] ?? null;

        if ($eta !== null && $eta !== '') {
            return sprintf('Tu paquete sale ahora. ETA: %s', $eta);
        }

        return 'Tu paquete ha salido para entrega. Te avisaremos cuando llegue.';
    }

    private function buildDeliveredMessage(array $payload): string
    {
        $time = $payload['delivered_at'] ?? $payload['timestamp'] ?? null;
        $signedBy = $payload['signed_by'] ?? $payload['recipient_name'] ?? null;

        if ($time !== null && $signedBy !== null) {
            return sprintf('Entregado a las %s. Firmado por: %s', $time, $signedBy);
        }

        if ($time !== null) {
            return sprintf('Tu paquete ha sido entregado a las %s.', $time);
        }

        return 'Tu paquete ha sido entregado correctamente.';
    }

    private function buildExceptionMessage(array $payload): string
    {
        $exceptionCode = $payload['exception_code'] ?? $payload['reason'] ?? 'UNKNOWN';
        $notes = $payload['notes'] ?? $payload['driver_notes'] ?? null;

        try {
            return $this->generateExceptionMessage($exceptionCode, $notes);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to generate AI exception message, using fallback: {error}', [
                'error' => $e->getMessage(),
                'exception_code' => $exceptionCode,
            ]);

            return $this->fallbackExceptionMessage($exceptionCode, $notes);
        }
    }

    private function fallbackExceptionMessage(string $exceptionCode, ?string $notes): string
    {
        $reason = $notes ?? $exceptionCode;

        return sprintf('No pudimos entregar hoy (%s). Nuevo intento programado.', $reason);
    }
}
