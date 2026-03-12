<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationTriggerType: string
{
    case Reminder = 'reminder';
    case PresenceCheck = 'presence_check';
    case Delivered = 'delivered';
    case DeliveryException = 'delivery_exception';
    case EtaChange = 'eta_change';
    case OutForDelivery = 'out_for_delivery';
}
