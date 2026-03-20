<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shipment\Model;

use App\Domain\Shipment\Model\Shipment;
use App\Domain\Shipment\Model\ShipmentEvent;
use App\Entity\Customer;
use App\Enum\ShipmentEventType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShipmentEventTest extends TestCase
{
    #[Test]
    public function constructorSetsFields(): void
    {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-001', $customer);
        $event = new ShipmentEvent($shipment, ShipmentEventType::DELIVERED, ['key' => 'value']);

        self::assertSame(ShipmentEventType::DELIVERED, $event->getEventType());
        self::assertSame(['key' => 'value'], $event->getPayload());
    }

    #[Test]
    public function constructorSetsCreatedAt(): void
    {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-001', $customer);
        $before = new \DateTimeImmutable();

        $event = new ShipmentEvent($shipment, ShipmentEventType::EXCEPTION);

        self::assertGreaterThanOrEqual($before, $event->getCreatedAt());
    }

    #[Test]
    public function defaultPayloadIsEmpty(): void
    {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-001', $customer);
        $event = new ShipmentEvent($shipment, ShipmentEventType::DELIVERED);

        self::assertSame([], $event->getPayload());
    }

    #[Test]
    public function payloadCanBeUpdated(): void
    {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-001', $customer);
        $event = new ShipmentEvent($shipment, ShipmentEventType::DELIVERED);

        $event->setPayload(['updated' => true]);
        self::assertSame(['updated' => true], $event->getPayload());
    }
}
