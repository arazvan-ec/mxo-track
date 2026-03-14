<?php

declare(strict_types=1);

namespace App\Enum;

enum RouteEventType: string
{
    // Lifecycle
    case CREATED = 'CREATED';
    case OPTIMIZED = 'OPTIMIZED';
    case ASSIGNED = 'ASSIGNED';
    case STARTED = 'STARTED';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';

    // Stop changes
    case STOP_DELIVERED = 'STOP_DELIVERED';
    case STOP_EXCEPTION = 'STOP_EXCEPTION';
    case STOP_SKIPPED = 'STOP_SKIPPED';

    // Optimization
    case REOPTIMIZED = 'REOPTIMIZED';
    case STOPS_REORDERED = 'STOPS_REORDERED';

    // Deviations
    case DEVIATION_DETECTED = 'DEVIATION_DETECTED';
    case DEVIATION_ENDED = 'DEVIATION_ENDED';
    case ETA_CHANGED = 'ETA_CHANGED';

    // External
    case NOTE_ADDED = 'NOTE_ADDED';
}
