<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Formatter\JsonFormatter as BaseJsonFormatter;
use Monolog\LogRecord;

/**
 * Structured JSON log formatter compatible with ELK/Datadog/CloudWatch.
 *
 * Adds contextual fields: timestamp (ISO 8601), request_id, user_id,
 * customer_id, and channel to every log entry.
 */
final class JsonFormatter extends BaseJsonFormatter
{
    public function __construct()
    {
        parent::__construct(self::BATCH_MODE_NEWLINES, true);
    }

    public function format(LogRecord $record): string
    {
        $extra = $record->extra;
        $context = $record->context;

        $entry = [
            'timestamp' => $record->datetime->format(\DATE_ATOM),
            'level' => $record->level->getName(),
            'channel' => $record->channel,
            'message' => $record->message,
            'request_id' => $extra['request_id'] ?? $context['request_id'] ?? null,
            'user_id' => $extra['user_id'] ?? $context['user_id'] ?? null,
            'customer_id' => $extra['customer_id'] ?? $context['customer_id'] ?? null,
        ];

        // Merge remaining context (excluding keys already promoted)
        $filteredContext = array_diff_key($context, array_flip(['request_id', 'user_id', 'customer_id']));
        if ($filteredContext !== []) {
            $entry['context'] = $filteredContext;
        }

        // Merge remaining extra (excluding keys already promoted)
        $filteredExtra = array_diff_key($extra, array_flip(['request_id', 'user_id', 'customer_id']));
        if ($filteredExtra !== []) {
            $entry['extra'] = $filteredExtra;
        }

        return $this->toJson($entry) . "\n";
    }
}
