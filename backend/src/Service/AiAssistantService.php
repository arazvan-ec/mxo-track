<?php

declare(strict_types=1);

namespace App\Service;

use App\Ai\LlmClientInterface;
use App\Ai\ToolDefinition;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\User;
use App\Repository\RouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class AiAssistantService
{
    private const int RATE_LIMIT_MAX = 20;
    private const int RATE_LIMIT_WINDOW = 60; // seconds

    /** @var array<string, list<float>> In-memory rate limit tracking per session */
    private static array $rateLimitBuckets = [];

    public function __construct(
        private readonly LlmClientInterface $llmClient,
        private readonly SearchService $searchService,
        private readonly ReportingService $reportingService,
        private readonly AlertService $alertService,
        private readonly ExceptionPatternService $exceptionPatternService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Process a chat message from an operator.
     *
     * @return array{response: string, tools_used: list<string>}
     */
    public function chat(string $userMessage, ?string $customerId = null, ?User $user = null): array
    {
        $rateLimitKey = 'user_' . ($user?->getId() ?? 'anon');
        if (!$this->checkRateLimit($rateLimitKey)) {
            return [
                'response' => 'Has alcanzado el limite de mensajes (20 por minuto). Espera un momento antes de enviar otro mensaje.',
                'tools_used' => [],
            ];
        }

        $systemPrompt = $this->buildSystemPrompt();
        $tools = $this->buildToolDefinitions();

        $messages = [
            ['role' => 'user', 'content' => $userMessage],
        ];

        $toolExecutor = function (string $toolName, array $toolInput) use ($customerId, $user): mixed {
            return $this->executeTool($toolName, $toolInput, $customerId, $user);
        };

        try {
            $result = $this->llmClient->completeWithToolLoop(
                $messages,
                $systemPrompt,
                $tools,
                $toolExecutor,
            );

            return [
                'response' => $result->content,
                'tools_used' => $result->rawResponse['tools_used'] ?? [],
            ];
        } catch (\Throwable $e) {
            $this->logger->error('AI Assistant error: ' . $e->getMessage());

            return [
                'response' => 'Lo siento, ocurrio un error al procesar tu consulta. Intentalo de nuevo.',
                'tools_used' => [],
            ];
        }
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
Eres un asistente de IA para operadores de logistica en la plataforma MXO Track.
Tu rol es ayudar a los operadores a obtener informacion rapida sobre envios, rutas, alertas y reportes.

Directrices:
- Responde siempre en espanol.
- Se conciso y directo en tus respuestas.
- Usa las herramientas disponibles para obtener datos actualizados del sistema.
- Si no encuentras informacion, dilo claramente.
- Formatea los datos de manera legible (usa listas, tablas simples con texto).
- Nunca inventes datos; solo reporta lo que devuelven las herramientas.
- Si el operador pregunta algo fuera del ambito logistico, redirigelo amablemente.

Contexto del sistema:
- MXO Track gestiona rutas de entrega, envios (shipments), vehiculos con GPS y conductores.
- Las rutas tienen paradas (stops) que pueden estar pendientes, entregadas, con excepcion o saltadas.
- Los envios pertenecen a clientes y se asignan a paradas en rutas.
- Las alertas incluyen vehiculos offline y rutas con muchas excepciones.
PROMPT;
    }

    /** @return list<ToolDefinition> */
    private function buildToolDefinitions(): array
    {
        return [
            new ToolDefinition('search_shipments', 'Buscar envios por numero de referencia, nombre del destinatario o direccion', [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Termino de busqueda (referencia, nombre o direccion)'],
                ],
                'required' => ['query'],
            ]),
            new ToolDefinition('get_delivery_report', 'Obtener reporte de entregas con totales, tasa de exito, desglose por conductor y cliente.', [
                'type' => 'object',
                'properties' => [
                    'from_date' => ['type' => 'string', 'description' => 'Fecha inicio en formato YYYY-MM-DD (opcional)'],
                    'to_date' => ['type' => 'string', 'description' => 'Fecha fin en formato YYYY-MM-DD (opcional)'],
                ],
                'required' => [],
            ]),
            new ToolDefinition('get_route_details', 'Obtener detalles de una ruta especifica incluyendo sus paradas, conductor asignado, estado y progreso', [
                'type' => 'object',
                'properties' => [
                    'route_public_id' => ['type' => 'string', 'description' => 'ID publico de la ruta (ULID)'],
                ],
                'required' => ['route_public_id'],
            ]),
            new ToolDefinition('get_active_alerts', 'Obtener alertas activas del sistema: vehiculos offline y rutas con excepciones excesivas', [
                'type' => 'object',
                'properties' => [],
                'required' => [],
            ]),
            new ToolDefinition('get_exception_patterns', 'Analizar patrones de excepciones en entregas: por codigo, por conductor, por direccion.', [
                'type' => 'object',
                'properties' => [
                    'from_date' => ['type' => 'string', 'description' => 'Fecha inicio en formato YYYY-MM-DD (opcional)'],
                    'to_date' => ['type' => 'string', 'description' => 'Fecha fin en formato YYYY-MM-DD (opcional)'],
                ],
                'required' => [],
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function executeTool(string $toolName, array $input, ?string $customerId, ?User $user): mixed
    {
        return match ($toolName) {
            'search_shipments' => $this->executeSearchShipments($input, $user),
            'get_delivery_report' => $this->executeGetDeliveryReport($input),
            'get_route_details' => $this->executeGetRouteDetails($input),
            'get_active_alerts' => $this->executeGetActiveAlerts(),
            'get_exception_patterns' => $this->executeGetExceptionPatterns($input),
            default => ['error' => 'Herramienta desconocida: ' . $toolName],
        };
    }

    /**
     * @param array<string, mixed> $input
     * @return list<array{type: string, label: string, url: string, extra: string}>
     */
    private function executeSearchShipments(array $input, ?User $user): array
    {
        $query = (string) ($input['query'] ?? '');
        if ($query === '') {
            return [];
        }

        return $this->searchService->search($query, $user);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function executeGetDeliveryReport(array $input): array
    {
        $from = $this->parseDate($input['from_date'] ?? null);
        $to = $this->parseDate($input['to_date'] ?? null, true);

        return $this->reportingService->getDeliveryReport($from, $to);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function executeGetRouteDetails(array $input): array
    {
        $publicId = (string) ($input['route_public_id'] ?? '');
        if ($publicId === '') {
            return ['error' => 'Se requiere el ID publico de la ruta'];
        }

        /** @var RouteRepository $routeRepo */
        $routeRepo = $this->em->getRepository(Route::class);
        $route = $routeRepo->findOneByPublicId($publicId);

        if ($route === null) {
            return ['error' => 'Ruta no encontrada con ID: ' . $publicId];
        }

        // Load stops
        $stops = $this->em->getRepository(RouteStop::class)->findBy(
            ['route' => $route],
            ['sequence' => 'ASC'],
        );

        $stopsData = [];
        foreach ($stops as $stop) {
            $stopsData[] = [
                'sequence' => $stop->getSequence(),
                'address' => $stop->getAddress(),
                'recipient' => $stop->getRecipientName(),
                'status' => $stop->getStatus()->value,
                'is_origin' => $stop->isOrigin(),
                'delivered_at' => $stop->getDeliveredAt()?->format('Y-m-d H:i:s'),
                'exception_code' => $stop->getExceptionCode()?->value,
                'exception_notes' => $stop->getExceptionNotes(),
            ];
        }

        $totalStops = \count($stops);
        $delivered = 0;
        $exceptions = 0;
        $pending = 0;
        foreach ($stops as $stop) {
            if ($stop->isOrigin()) {
                $totalStops--;
                continue;
            }
            match ($stop->getStatus()->value) {
                'delivered' => $delivered++,
                'exception' => $exceptions++,
                'pending' => $pending++,
                default => null,
            };
        }

        return [
            'route_id' => $route->getPublicIdString(),
            'name' => $route->getName(),
            'status' => $route->getStatus()->value,
            'driver' => $route->getDriver()?->getName() ?? $route->getDriver()?->getEmail() ?? 'Sin asignar',
            'vehicle' => $route->getVehicle()?->getName() ?? 'Sin asignar',
            'customer' => $route->getCustomer()?->getName() ?? 'Sin asignar',
            'start_at' => $route->getStartAt()?->format('Y-m-d H:i:s'),
            'end_at' => $route->getEndAt()?->format('Y-m-d H:i:s'),
            'progress' => [
                'total_stops' => $totalStops,
                'delivered' => $delivered,
                'exceptions' => $exceptions,
                'pending' => $pending,
            ],
            'stops' => $stopsData,
        ];
    }

    /**
     * @return array{offline_vehicles: list<array{name: string, minutes_offline: int}>, routes_with_excessive_exceptions: list<array{name: string, route_id: string, status: string}>}
     */
    private function executeGetActiveAlerts(): array
    {
        $offlineVehicles = $this->alertService->getOfflineVehicles(30);

        $offlineData = [];
        foreach ($offlineVehicles as $item) {
            $vehicle = $item['vehicle'];
            $offlineData[] = [
                'name' => $vehicle->getName(),
                'minutes_offline' => $item['minutesOffline'],
            ];
        }

        // Check active routes for excessive exceptions
        $activeRoutes = $this->em->getRepository(Route::class)->findBy([
            'status' => \App\Enum\RouteStatus::ACTIVE,
        ]);

        $routesWithExceptions = [];
        foreach ($activeRoutes as $route) {
            if ($this->alertService->checkExcessiveExceptions($route, 3)) {
                $routesWithExceptions[] = [
                    'name' => $route->getName(),
                    'route_id' => $route->getPublicIdString(),
                    'status' => $route->getStatus()->value,
                ];
            }
        }

        return [
            'offline_vehicles' => $offlineData,
            'routes_with_excessive_exceptions' => $routesWithExceptions,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function executeGetExceptionPatterns(array $input): array
    {
        $from = $this->parseDate($input['from_date'] ?? null);
        $to = $this->parseDate($input['to_date'] ?? null, true);

        return $this->exceptionPatternService->analyzePatterns($from, $to);
    }

    private function parseDate(mixed $value, bool $endOfDay = false): ?\DateTimeImmutable
    {
        if (!\is_string($value) || $value === '') {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($value);

            return $endOfDay ? $date->setTime(23, 59, 59) : $date->setTime(0, 0, 0);
        } catch (\Exception) {
            return null;
        }
    }

    private function checkRateLimit(string $key): bool
    {
        $now = microtime(true);
        $window = $now - self::RATE_LIMIT_WINDOW;

        if (!isset(self::$rateLimitBuckets[$key])) {
            self::$rateLimitBuckets[$key] = [];
        }

        // Remove old entries
        self::$rateLimitBuckets[$key] = array_values(array_filter(
            self::$rateLimitBuckets[$key],
            static fn(float $ts) => $ts > $window,
        ));

        if (\count(self::$rateLimitBuckets[$key]) >= self::RATE_LIMIT_MAX) {
            return false;
        }

        self::$rateLimitBuckets[$key][] = $now;

        return true;
    }
}
