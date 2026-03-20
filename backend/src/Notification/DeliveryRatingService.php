<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\DeliveryRating;
use App\Entity\Shipment;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class DeliveryRatingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string[]|null $tags
     */
    public function submitRating(
        Shipment $shipment,
        int $score,
        ?string $comment = null,
        ?array $tags = null,
        ?string $phone = null,
    ): DeliveryRating {
        $existing = $this->getRatingForShipment($shipment);

        if ($existing !== null) {
            throw new \LogicException(sprintf(
                'Shipment "%s" already has a rating',
                $shipment->getReference(),
            ));
        }

        $rating = new DeliveryRating($shipment, $score);
        $rating->setComment($comment);
        $rating->setTags($tags);
        $rating->setRecipientPhone($phone);

        $this->entityManager->persist($rating);
        $this->entityManager->flush();

        $this->logger->info('Rating {score}/5 submitted for shipment {shipment}', [
            'score' => $score,
            'shipment' => $shipment->getReference(),
        ]);

        return $rating;
    }

    public function getRatingForShipment(Shipment $shipment): ?DeliveryRating
    {
        return $this->entityManager->getRepository(DeliveryRating::class)->findOneBy([
            'shipment' => $shipment,
        ]);
    }

    public function getAverageRatingForDriver(int $driverId): float
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('AVG(r.score)')
            ->from(DeliveryRating::class, 'r')
            ->join('r.shipment', 's')
            ->join(\App\Domain\Route\Model\RouteStop::class, 'rs', 'WITH', 'rs.shipment = s')
            ->join('rs.route', 'rt')
            ->where('rt.driver = :driverId')
            ->setParameter('driverId', $driverId);

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result !== null ? round((float) $result, 2) : 0.0;
    }
}
