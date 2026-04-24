<?php

declare(strict_types=1);

namespace App\Infrastructure\Route\Doctrine;

use App\Domain\Route\Repository\RouteStopRepositoryInterface;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Enum\RouteStopStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineRouteStopRepository implements RouteStopRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function findOneByPublicId(string $publicId): ?RouteStop
    {
        try {
            return $this->em->getRepository(RouteStop::class)
                ->findOneBy(['publicId' => Ulid::fromString($publicId)]);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public function findByRoute(Route $route): array
    {
        return $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $route)
            ->orderBy('s.sequence', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countsByRoutes(array $routes): array
    {
        if (\count($routes) === 0) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(s.route) as routeId, COUNT(s.id) as total, SUM(CASE WHEN s.status = :delivered THEN 1 ELSE 0 END) as delivered')
            ->from(RouteStop::class, 's')
            ->where('s.route IN (:routes)')
            ->setParameter('routes', $routes)
            ->setParameter('delivered', RouteStopStatus::DELIVERED)
            ->groupBy('s.route')
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['routeId']] = [
                'total' => (int) $row['total'],
                'delivered' => (int) $row['delivered'],
            ];
        }

        return $out;
    }

    public function findNextPendingStopsByRoutes(array $routes): array
    {
        if (\count($routes) === 0) {
            return [];
        }

        // Single query: hydrate only the (route, MIN(sequence)) pair per route.
        // Uses a correlated subquery in DQL to avoid fetching all pending stops.
        $sub = $this->em->createQueryBuilder()
            ->select('IDENTITY(s2.route) as routeId, MIN(s2.sequence) as minSeq')
            ->from(RouteStop::class, 's2')
            ->where('s2.route IN (:routes) AND s2.status = :pending')
            ->groupBy('s2.route')
            ->setParameter('routes', $routes)
            ->setParameter('pending', RouteStopStatus::PENDING)
            ->getQuery()
            ->getResult();

        if (\count($sub) === 0) {
            return [];
        }

        // Build (routeId, minSeq) map, then fetch exactly those rows.
        $pairs = [];
        foreach ($sub as $row) {
            $pairs[] = ['r' => (string) $row['routeId'], 's' => (int) $row['minSeq']];
        }

        // Build a WHERE clause with OR of (route=:rN AND sequence=:sN) pairs.
        // Bounded by the number of routes (≤100 per list page), no N+1.
        $qb = $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's');
        $expr = $qb->expr();
        $ors = [];
        foreach ($pairs as $i => $pair) {
            $ors[] = $expr->andX(
                $expr->eq('IDENTITY(s.route)', ':r' . $i),
                $expr->eq('s.sequence', ':s' . $i),
            );
            $qb->setParameter('r' . $i, $pair['r']);
            $qb->setParameter('s' . $i, $pair['s']);
        }
        $qb->where($expr->orX(...$ors));

        /** @var list<RouteStop> $stops */
        $stops = $qb->getQuery()->getResult();

        $out = [];
        foreach ($stops as $stop) {
            $routeId = $stop->getRoute()->getId();
            if ($routeId === null) {
                continue;
            }
            $out[$routeId] = [
                'sequence' => $stop->getSequence(),
                'address' => $stop->getAddress(),
                'recipientName' => $stop->getRecipientName(),
                'windowStart' => $stop->getDeliveryWindowStart()?->format(\DateTimeInterface::ATOM),
                'windowEnd' => $stop->getDeliveryWindowEnd()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return $out;
    }

    public function findDeliveryHistogramsByRoutes(array $routes, \DateTimeZone $tz, \DateTimeImmutable $day): array
    {
        if (\count($routes) === 0) {
            return [];
        }

        // Bound the day to [midnight, next midnight) in the given timezone.
        $start = $day->setTimezone($tz)->setTime(0, 0, 0);
        $end = $start->modify('+1 day');

        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(s.route) as routeId, s.deliveredAt as deliveredAt')
            ->from(RouteStop::class, 's')
            ->where('s.route IN (:routes) AND s.status = :delivered AND s.deliveredAt >= :start AND s.deliveredAt < :end')
            ->setParameter('routes', $routes)
            ->setParameter('delivered', RouteStopStatus::DELIVERED)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        if (\count($rows) === 0) {
            return [];
        }

        /** @var array<string, list<int>> $out */
        $out = [];
        foreach ($rows as $row) {
            $routeId = (string) $row['routeId'];
            $deliveredAt = $row['deliveredAt'];
            if (!$deliveredAt instanceof \DateTimeInterface) {
                continue;
            }
            // Interpret the hour in the caller-supplied timezone for consistency.
            $local = \DateTimeImmutable::createFromInterface($deliveredAt)->setTimezone($tz);
            if (!isset($out[$routeId])) {
                $out[$routeId] = array_fill(0, 24, 0);
            }
            $hour = (int) $local->format('G');
            if ($hour >= 0 && $hour <= 23) {
                ++$out[$routeId][$hour];
            }
        }

        return $out;
    }

    public function save(RouteStop $stop): void
    {
        $this->em->persist($stop);
    }

    public function remove(RouteStop $stop): void
    {
        $this->em->remove($stop);
    }

    public function flush(): void
    {
        $this->em->flush();
    }
}
