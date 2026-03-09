<?php
declare(strict_types=1);
namespace App\Realtime;

interface RealtimePublisherInterface
{
    /**
     * Publish a single SSE message to the configured hub.
     */
    public function publish(SseMessage $message): void;

    /**
     * Publish multiple SSE messages in sequence.
     *
     * @param list<SseMessage> $messages
     */
    public function publishBatch(array $messages): void;
}
