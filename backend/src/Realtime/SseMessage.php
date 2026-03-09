<?php
declare(strict_types=1);
namespace App\Realtime;

final readonly class SseMessage
{
    /**
     * @param string|array<string, mixed> $data    Payload to publish (string or array, serialized to JSON)
     * @param list<string>                $topics  One or more topic IRIs
     */
    public function __construct(
        public string|array $data,
        public array $topics = [],
        public ?string $id = null,
        public ?string $type = null,
        public ?int $retry = null,
        public bool $private = false,
    ) {
    }
}
