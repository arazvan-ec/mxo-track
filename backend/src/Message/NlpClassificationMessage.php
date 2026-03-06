<?php

declare(strict_types=1);

namespace App\Message;

final readonly class NlpClassificationMessage
{
    public function __construct(
        public int $shipmentEventId,
        public string $exceptionNotes,
        public string $exceptionCode,
    ) {
    }
}
