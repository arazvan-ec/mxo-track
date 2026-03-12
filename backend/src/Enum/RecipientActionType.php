<?php

declare(strict_types=1);

namespace App\Enum;

enum RecipientActionType: string
{
    case PresenceConfirmed = 'presence_confirmed';
    case PresenceDenied = 'presence_denied';
    case RescheduleRequested = 'reschedule_requested';
    case AlternativeRequested = 'alternative_requested';
    case RatingSubmitted = 'rating_submitted';
    case TrackingPageViewed = 'tracking_page_viewed';
}
