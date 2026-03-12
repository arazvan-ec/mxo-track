<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\NotificationChannel;
use App\Enum\NotificationTriggerType;
use App\Notification\NotificationDispatcher;
use App\Repository\NotificationLogRepository;
use App\Repository\ShipmentRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:notifications:schedule',
    description: 'Schedule reminder and presence check notifications',
)]
final class ScheduleNotificationsCommand extends Command
{
    public function __construct(
        private readonly ShipmentRepository $shipmentRepo,
        private readonly NotificationLogRepository $logRepo,
        private readonly NotificationDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->scheduleReminders($output);
        $this->schedulePresenceChecks($output);

        return Command::SUCCESS;
    }

    private function scheduleReminders(OutputInterface $output): void
    {
        $shipments = $this->shipmentRepo->findForTomorrow();

        foreach ($shipments as $shipment) {
            if ($this->logRepo->hasBeenSent($shipment, NotificationTriggerType::Reminder, NotificationChannel::Sms)) {
                continue;
            }

            $this->dispatcher->dispatchForShipment($shipment, NotificationTriggerType::Reminder);
            $output->writeln(sprintf('Reminder dispatched for shipment %s', $shipment->getReference()));
        }
    }

    private function schedulePresenceChecks(OutputInterface $output): void
    {
        $shipments = $this->shipmentRepo->findWithEstimatedDeliveryWithinMinutes(45);

        foreach ($shipments as $shipment) {
            if ($this->logRepo->hasBeenSent($shipment, NotificationTriggerType::PresenceCheck, NotificationChannel::Sms)) {
                continue;
            }

            $this->dispatcher->dispatchForShipment($shipment, NotificationTriggerType::PresenceCheck);
            $output->writeln(sprintf('Presence check dispatched for shipment %s', $shipment->getReference()));
        }
    }
}
