<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Event\VehiclePositionReceived;
use App\Domain\Route\Model\Route;
use App\Entity\Vehicle;
use App\Enum\RouteStatus;
use App\Message\FleetAnomalyCheckMessage;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener]
final readonly class FleetAnomalyCheckListener
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private VehicleRepository $vehicleRepo,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(VehiclePositionReceived $event): void
    {
        $vehicle = $this->vehicleRepo->findOneByPublicId($event->vehiclePublicId);

        if (!$vehicle instanceof Vehicle) {
            return;
        }

        $activeRoute = $this->em->getRepository(Route::class)->findOneBy([
            'vehicle' => $vehicle,
            'status' => RouteStatus::ACTIVE,
        ]);

        if ($activeRoute === null) {
            return;
        }

        $this->messageBus->dispatch(new FleetAnomalyCheckMessage(
            $vehicle->getId(),
            $activeRoute->getId(),
        ));

        $this->logger->debug('FleetAnomalyCheckListener: dispatched anomaly check for vehicle {vehicle}, route {route}', [
            'vehicle' => $vehicle->getName(),
            'route' => $activeRoute->getName(),
        ]);
    }
}
