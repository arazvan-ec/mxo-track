<?php
declare(strict_types=1);
namespace App\Ai;

final readonly class ToolDefinition
{
    /**
     * @param array<string, mixed> $inputSchema JSON Schema for tool parameters
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema = [],
    ) {
    }
}
