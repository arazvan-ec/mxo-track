<?php

declare(strict_types=1);

namespace App\Notification;

use App\Enum\NotificationTriggerType;

final class DefaultNotificationTiming
{
    /** @var array<string, array<string, int>> */
    private const DEFAULTS = [
        'reminder' => ['hours_before' => 12],
        'presence_check' => ['minutes_before' => 30],
        'delivered' => ['delay_minutes' => 5],
        'delivery_exception' => ['delay_minutes' => 10],
        'eta_change' => ['threshold_minutes' => 15],
        'out_for_delivery' => [],
    ];

    /**
     * @param array<string, int> $customConfig
     * @return array<string, int>
     */
    public static function resolve(
        NotificationTriggerType $trigger,
        array $customConfig,
    ): array {
        return empty($customConfig)
            ? self::DEFAULTS[$trigger->value]
            : $customConfig;
    }
}
