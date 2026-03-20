<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Customer;
use App\Entity\RecipientAction;
use App\Domain\Shipment\Model\Shipment;
use App\Enum\RecipientActionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecipientAction::class)]
final class RecipientActionTest extends TestCase
{
    #[Test]
    public function it_stores_all_required_fields(): void
    {
        $customer = new Customer('Test Corp');
        $shipment = new Shipment('REF-001', $customer);

        $action = new RecipientAction(
            shipment: $shipment,
            actionType: RecipientActionType::PresenceConfirmed,
            payload: ['confirmed' => true],
        );

        self::assertSame($shipment, $action->getShipment());
        self::assertSame(RecipientActionType::PresenceConfirmed, $action->getActionType());
        self::assertSame(['confirmed' => true], $action->getPayload());
        self::assertInstanceOf(\DateTimeImmutable::class, $action->getCreatedAt());
    }

    #[Test]
    public function it_defaults_payload_to_empty_array(): void
    {
        $customer = new Customer('Test Corp');
        $shipment = new Shipment('REF-001', $customer);

        $action = new RecipientAction(
            shipment: $shipment,
            actionType: RecipientActionType::TrackingPageViewed,
        );

        self::assertSame([], $action->getPayload());
    }

    #[Test]
    public function it_stores_reschedule_payload(): void
    {
        $customer = new Customer('Test Corp');
        $shipment = new Shipment('REF-001', $customer);

        $action = new RecipientAction(
            shipment: $shipment,
            actionType: RecipientActionType::RescheduleRequested,
            payload: ['slot_date' => '2026-03-15', 'slot_time_range' => '09:00-13:00'],
        );

        self::assertSame('2026-03-15', $action->getPayload()['slot_date']);
        self::assertSame('09:00-13:00', $action->getPayload()['slot_time_range']);
    }
}
