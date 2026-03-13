<?php

declare(strict_types=1);

namespace App\Application\Route;

final readonly class BuildRoutesResult
{
    /**
     * @param array<array{route: array<string, mixed>, stopsCount: int, validation: mixed}> $routes
     * @param array|null $optimizationLog Structured log of optimization decisions
     */
    public function __construct(
        public int $routesCreated,
        public array $routes,
        public ?array $optimizationLog = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'routesCreated' => $this->routesCreated,
            'routes' => $this->routes,
        ];

        if ($this->optimizationLog !== null) {
            $result['optimizationLog'] = $this->optimizationLog;
        }

        return $result;
    }
}
