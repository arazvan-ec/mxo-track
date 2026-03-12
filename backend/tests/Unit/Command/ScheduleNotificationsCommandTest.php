<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ScheduleNotificationsCommand;
use App\Entity\Customer;
use App\Entity\Shipment;
use App\Enum\NotificationTriggerType;
use App\Notification\NotificationDispatcher;
use App\Repository\NotificationLogRepository;
use App\Repository\ShipmentRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ScheduleNotificationsCommand::class)]
final class ScheduleNotificationsCommandTest extends TestCase
{
    private ShipmentRepository&MockObject $shipmentRepo;
    private NotificationLogRepository&MockObject $logRepo;
    private NotificationDispatcher&MockObject $dispatcher;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->shipmentRepo = $this->createMock(ShipmentRepository::class);
        $this->logRepo = $this->createMock(NotificationLogRepository::class);
        $this->dispatcher = $this->createMock(NotificationDispatcher::class);

        $command = new ScheduleNotificationsCommand(
            $this->shipmentRepo,
            $this->logRepo,
            $this->dispatcher,
        );

        $app = new Application();
        $app->add($command);
        $this->tester = new CommandTester($command);
    }

    #[Test]
    public function it_dispatches_reminders_for_tomorrows_shipments(): void
    {
        $customer = new Customer('Test Corp');
        $s1 = new Shipment('REF-001', $customer);
        $s1->setRecipientPhone('+34600000001');
        $s2 = new Shipment('REF-002', $customer);
        $s2->setRecipientPhone('+34600000002');

        $this->shipmentRepo->method('findForTomorrow')->willReturn([$s1, $s2]);
        $this->shipmentRepo->method('findWithEstimatedDeliveryWithinMinutes')->willReturn([]);
        $this->logRepo->method('hasBeenSent')->willReturn(false);

        $this->dispatcher->expects(self::exactly(2))
            ->method('dispatchForShipment')
            ->with(
                self::isInstanceOf(Shipment::class),
                NotificationTriggerType::Reminder,
            );

        $this->tester->execute([]);
        self::assertSame(0, $this->tester->getStatusCode());
    }

    #[Test]
    public function it_skips_shipments_with_reminder_already_sent(): void
    {
        $customer = new Customer('Test Corp');
        $s1 = new Shipment('REF-001', $customer);
        $s1->setRecipientPhone('+34600000001');

        $this->shipmentRepo->method('findForTomorrow')->willReturn([$s1]);
        $this->shipmentRepo->method('findWithEstimatedDeliveryWithinMinutes')->willReturn([]);
        $this->logRepo->method('hasBeenSent')->willReturn(true);

        $this->dispatcher->expects(self::never())->method('dispatchForShipment');

        $this->tester->execute([]);
        self::assertSame(0, $this->tester->getStatusCode());
    }
}
