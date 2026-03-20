<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\Domain;

use App\Domain\Event\StopDelivered;
use App\Domain\Event\StopExceptionReported;
use App\Entity\Customer;
use App\Domain\Route\Model\Route;
use App\Domain\Shipment\Model\Shipment;
use App\Enum\ExceptionCode;
use App\Enum\NotificationTriggerType;
use App\EventListener\Domain\RecipientNotificationListener;
use App\Notification\NotificationDispatcher;
use App\Repository\ShipmentRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecipientNotificationListener::class)]
final class RecipientNotificationListenerTest extends TestCase
{
    private ShipmentRepository&MockObject $shipmentRepo;
    private NotificationDispatcher&MockObject $dispatcher;
    private RecipientNotificationListener $listener;

    protected function setUp(): void
    {
        $this->shipmentRepo = $this->createMock(ShipmentRepository::class);
        $this->dispatcher = $this->createMock(NotificationDispatcher::class);
        $this->listener = new RecipientNotificationListener($this->shipmentRepo, $this->dispatcher);
    }

    #[Test]
    public function it_dispatches_delivered_notification(): void
    {
        $customer = new Customer('Test Corp');
        $shipment = new Shipment('REF-001', $customer);
        $shipment->setRecipientPhone('+34600000001');

        $this->shipmentRepo->method('findOneByPublicId')->willReturn($shipment);
        $this->dispatcher->expects(self::once())
            ->method('dispatchForShipment')
            ->with($shipment, NotificationTriggerType::Delivered);

        $event = new StopDelivered(
            stopPublicId: '01ABCDEF',
            shipmentPublicId: '01SHIPMENT',
            routePublicId: '01ROUTE',
            driverUserId: 1,
            podPublicId: '01POD',
        );

        $this->listener->onStopDelivered($event);
    }

    #[Test]
    public function it_dispatches_exception_notification(): void
    {
        $customer = new Customer('Test Corp');
        $shipment = new Shipment('REF-001', $customer);
        $shipment->setRecipientPhone('+34600000001');

        $this->shipmentRepo->method('findOneByPublicId')->willReturn($shipment);
        $this->dispatcher->expects(self::once())
            ->method('dispatchForShipment')
            ->with($shipment, NotificationTriggerType::DeliveryException);

        $event = new StopExceptionReported(
            stopPublicId: '01ABCDEF',
            shipmentPublicId: '01SHIPMENT',
            routePublicId: '01ROUTE',
            driverUserId: 1,
            reason: ExceptionCode::ABSENT,
            notes: 'Not home',
        );

        $this->listener->onStopExceptionReported($event);
    }

    #[Test]
    public function it_skips_when_shipment_not_found(): void
    {
        $this->shipmentRepo->method('findOneByPublicId')->willReturn(null);
        $this->dispatcher->expects(self::never())->method('dispatchForShipment');

        $event = new StopDelivered(
            stopPublicId: '01ABCDEF',
            shipmentPublicId: '01MISSING',
            routePublicId: '01ROUTE',
            driverUserId: 1,
            podPublicId: '01POD',
        );

        $this->listener->onStopDelivered($event);
    }
}
