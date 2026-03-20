<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Entity\Customer;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Domain\Shipment\Model\Shipment;
use App\Enum\NotificationTriggerType;
use App\Notification\NotificationDispatcher;
use App\Notification\RecipientNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(RecipientNotificationService::class)]
final class RecipientNotificationServiceTest extends TestCase
{
    private NotificationDispatcher $dispatcher;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(NotificationDispatcher::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
    }

    private function createService(): RecipientNotificationService
    {
        return new RecipientNotificationService(
            $this->dispatcher,
            $this->em,
            new NullLogger(),
        );
    }

    #[Test]
    public function notify_approaching_dispatches_via_notification_dispatcher(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getRecipientPhone')->willReturn('+34600000000');

        $stop = $this->createMock(RouteStop::class);
        $stop->method('getShipment')->willReturn($shipment);
        $stop->method('getRecipientPhone')->willReturn('+34600000000');
        $route = $this->createMock(Route::class);
        $stop->method('getRoute')->willReturn($route);

        $this->dispatcher->expects(self::once())
            ->method('dispatchForShipment')
            ->with($shipment, NotificationTriggerType::PresenceCheck);

        $service = $this->createService();
        $service->notifyApproaching($stop);
    }

    #[Test]
    public function notify_approaching_does_nothing_when_shipment_null(): void
    {
        $stop = $this->createMock(RouteStop::class);
        $stop->method('getShipment')->willReturn(null);

        $this->dispatcher->expects(self::never())->method('dispatchForShipment');

        $service = $this->createService();
        $service->notifyApproaching($stop);
    }

    #[Test]
    public function notify_approaching_does_nothing_when_no_phone(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getRecipientPhone')->willReturn(null);

        $stop = $this->createMock(RouteStop::class);
        $stop->method('getShipment')->willReturn($shipment);
        $stop->method('getRecipientPhone')->willReturn(null);

        $this->dispatcher->expects(self::never())->method('dispatchForShipment');

        $service = $this->createService();
        $service->notifyApproaching($stop);
    }

    #[Test]
    public function notify_delivered_dispatches_via_notification_dispatcher(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getRecipientPhone')->willReturn('+34600000000');

        $route = $this->createMock(Route::class);

        $stop = $this->createMock(RouteStop::class);
        $stop->method('getShipment')->willReturn($shipment);
        $stop->method('getRecipientPhone')->willReturn('+34600000000');
        $stop->method('getRoute')->willReturn($route);

        $this->dispatcher->expects(self::once())
            ->method('dispatchForShipment')
            ->with($shipment, NotificationTriggerType::Delivered);

        $service = $this->createService();
        $service->notifyDelivered($stop);
    }

    #[Test]
    public function notify_route_started_dispatches_per_stop(): void
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn('10');

        $route = $this->createMock(Route::class);
        $route->method('getCustomer')->willReturn($customer);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getRecipientPhone')->willReturn('+34600000000');

        $originStop = $this->createMock(RouteStop::class);
        $originStop->method('isOrigin')->willReturn(true);

        $deliveryStop = $this->createMock(RouteStop::class);
        $deliveryStop->method('isOrigin')->willReturn(false);
        $deliveryStop->method('getShipment')->willReturn($shipment);
        $deliveryStop->method('getRecipientPhone')->willReturn('+34600000000');

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$originStop, $deliveryStop]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->em->method('createQueryBuilder')->willReturn($qb);

        $this->dispatcher->expects(self::once())
            ->method('dispatchForShipment')
            ->with($shipment, NotificationTriggerType::OutForDelivery);

        $service = $this->createService();
        $service->notifyRouteStarted($route);
    }
}
