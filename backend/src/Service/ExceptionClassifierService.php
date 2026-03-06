<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class ExceptionClassifierService
{
    private const int RATE_LIMIT_MAX_REQUESTS = 30;

    private const array VALID_SUBCATEGORIES = [
        'ACCESO_EDIFICIO',
        'DIRECCION_INCOMPLETA',
        'AUSENCIA_RECURRENTE',
        'RECHAZO_ESTADO',
        'HORARIO_INCOMPATIBLE',
        'PAQUETE_DANADO',
        'DIRECCION_NO_ENCONTRADA',
        'DESTINATARIO_DESCONOCIDO',
        'OTRO',
    ];

    public function __construct(
        private readonly ClaudeApiClient $claudeApi,
        private readonly RateLimitedApiClient $rateLimiter,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Clasifica las notas de una excepcion de entrega usando Claude API.
     *
     * @return array{subcategory: string, actionable_insight: ?string, suggested_action: ?string, confidence: float}
     */
    public function classify(string $exceptionNotes, string $exceptionCode): array
    {
        $fallback = [
            'subcategory' => 'OTRO',
            'actionable_insight' => null,
            'suggested_action' => null,
            'confidence' => 0.0,
        ];

        if (trim($exceptionNotes) === '') {
            return $fallback;
        }

        $prompt = $this->buildPrompt($exceptionNotes, $exceptionCode);

        try {
            $response = $this->rateLimiter->execute(
                fn () => $this->claudeApi->sendMessage($prompt),
                self::RATE_LIMIT_MAX_REQUESTS,
            );

            return $this->parseResponse($response, $fallback);
        } catch (\Throwable $e) {
            $this->logger->warning('Exception classification failed: {message}', [
                'message' => $e->getMessage(),
                'exceptionCode' => $exceptionCode,
            ]);

            return $fallback;
        }
    }

    private function buildPrompt(string $exceptionNotes, string $exceptionCode): string
    {
        $subcategories = implode(', ', self::VALID_SUBCATEGORIES);

        return <<<PROMPT
            Eres un sistema de clasificacion de excepciones de entrega de paqueteria.
            Analiza las notas del conductor y clasifica la excepcion en una subcategoria.

            Codigo de excepcion original: {$exceptionCode}
            Notas del conductor: {$exceptionNotes}

            Subcategorias disponibles: {$subcategories}

            Guia de subcategorias:
            - ACCESO_EDIFICIO: Problemas para acceder al edificio (portero no abre, puerta cerrada, codigo de acceso incorrecto)
            - DIRECCION_INCOMPLETA: Falta informacion en la direccion (sin numero de piso, sin portal, sin escalera)
            - AUSENCIA_RECURRENTE: El destinatario no esta en casa repetidamente o no contesta
            - RECHAZO_ESTADO: El destinatario rechaza el paquete por su estado o contenido
            - HORARIO_INCOMPATIBLE: No se puede entregar en el horario disponible
            - PAQUETE_DANADO: El paquete esta danado o deteriorado
            - DIRECCION_NO_ENCONTRADA: La direccion no existe o no se puede localizar
            - DESTINATARIO_DESCONOCIDO: El destinatario no es conocido en la direccion indicada
            - OTRO: No encaja en ninguna de las categorias anteriores

            Responde UNICAMENTE con un JSON valido (sin markdown, sin texto adicional) con este formato:
            {
                "subcategory": "SUBCATEGORIA",
                "actionable_insight": "Descripcion breve del problema detectado",
                "suggested_action": "Accion sugerida para resolver el problema",
                "confidence": 0.85
            }

            El campo confidence debe ser un numero entre 0 y 1 indicando tu nivel de confianza en la clasificacion.
            PROMPT;
    }

    private function parseResponse(string $response, array $fallback): array
    {
        $decoded = json_decode(trim($response), true);

        if (!is_array($decoded)) {
            $this->logger->warning('Failed to decode Claude response as JSON', [
                'response' => mb_substr($response, 0, 500),
            ]);

            return $fallback;
        }

        $subcategory = $decoded['subcategory'] ?? 'OTRO';
        if (!in_array($subcategory, self::VALID_SUBCATEGORIES, true)) {
            $subcategory = 'OTRO';
        }

        $confidence = (float) ($decoded['confidence'] ?? 0.0);
        $confidence = max(0.0, min(1.0, $confidence));

        return [
            'subcategory' => $subcategory,
            'actionable_insight' => isset($decoded['actionable_insight']) ? (string) $decoded['actionable_insight'] : null,
            'suggested_action' => isset($decoded['suggested_action']) ? (string) $decoded['suggested_action'] : null,
            'confidence' => $confidence,
        ];
    }
}
