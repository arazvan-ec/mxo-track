<?php

declare(strict_types=1);

namespace App\Application\Route;

final readonly class BuildRoutesResult
{
    /**
     * @param array<array{route: array<string, mixed>, stopsCount: int, validation: mixed}> $routes
     */
    public function __construct(
        public int $routesCreated,
        public array $routes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'routesCreated' => $this->routesCreated,
            'routes' => $this->routes,
        ];
    }
}
