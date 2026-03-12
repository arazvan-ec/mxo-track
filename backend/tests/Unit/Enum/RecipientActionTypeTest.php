<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\RecipientActionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecipientActionType::class)]
class RecipientActionTypeTest extends TestCase
{
    #[Test]
    public function it_has_all_expected_cases(): void
    {
        $cases = RecipientActionType::cases();
        self::assertCount(6, $cases);
        self::assertSame('presence_confirmed', RecipientActionType::PresenceConfirmed->value);
        self::assertSame('presence_denied', RecipientActionType::PresenceDenied->value);
        self::assertSame('reschedule_requested', RecipientActionType::RescheduleRequested->value);
        self::assertSame('alternative_requested', RecipientActionType::AlternativeRequested->value);
        self::assertSame('rating_submitted', RecipientActionType::RatingSubmitted->value);
        self::assertSame('tracking_page_viewed', RecipientActionType::TrackingPageViewed->value);
    }
}
