<?php

declare(strict_types=1);

namespace App\Infrastructure\MapView\Controller;

use App\Entity\RouteStop;
use App\Enum\ExceptionCode;
use App\Enum\RouteStopStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ExceptionMapDataController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/map/exceptions', name: 'api_map_exceptions', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $fromStr = $request->query->getString('from', '');
        $toStr = $request->query->getString('to', '');

        $qb = $this->em->createQueryBuilder()
            ->select(
                'rs.latitude AS lat',
                'rs.longitude AS lng',
                'rs.address AS address',
                'rs.exceptionCode AS type',
                'rs.exceptionNotes AS notes',
                'r.name AS routeName',
                'r.startAt AS date',
            )
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('rs.status = :exception')
            ->andWhere('rs.isOrigin = false')
            ->andWhere('rs.latitude IS NOT NULL')
            ->andWhere('rs.longitude IS NOT NULL')
            ->setParameter('exception', RouteStopStatus::EXCEPTION)
            ->orderBy('r.startAt', 'DESC');

        if ($fromStr !== '') {
            try {
                $from = new \DateTimeImmutable($fromStr);
                $qb->andWhere('r.startAt >= :from')
                    ->setParameter('from', $from->setTime(0, 0, 0));
            } catch (\Exception) {
                // ignore invalid date
            }
        }

        if ($toStr !== '') {
            try {
                $to = new \DateTimeImmutable($toStr);
                $qb->andWhere('r.startAt <= :to')
                    ->setParameter('to', $to->setTime(23, 59, 59));
            } catch (\Exception) {
                // ignore invalid date
            }
        }

        $results = $qb->setMaxResults(5000)->getQuery()->getResult();

        $exceptions = array_map(static fn(array $row) => [
            'lat' => (float) $row['lat'],
            'lng' => (float) $row['lng'],
            'address' => (string) $row['address'],
            'type' => $row['type'] instanceof ExceptionCode
                ? $row['type']->value
                : (string) ($row['type'] ?? 'OTHER'),
            'routeName' => (string) $row['routeName'],
            'date' => $row['date'] instanceof \DateTimeInterface
                ? $row['date']->format('Y-m-d')
                : null,
            'notes' => $row['notes'],
        ], $results);

        return $this->json(['exceptions' => $exceptions]);
    }
}
