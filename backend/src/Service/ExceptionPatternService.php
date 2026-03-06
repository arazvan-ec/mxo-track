<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ShipmentEvent;
use App\Enum\ShipmentEventType;
use Doctrine\ORM\EntityManagerInterface;

final class ExceptionPatternService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Obtiene patrones de excepciones clasificadas por IA, agregados por subcategoria.
     *
     * @return array<int, array{subcategory: string, count: int, percentage: float}>
     */
    public function getPatterns(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('se.payload')
            ->from(ShipmentEvent::class, 'se')
            ->where('se.eventType = :eventType')
            ->setParameter('eventType', ShipmentEventType::EXCEPTION);

        if ($from !== null) {
            $qb->andWhere('se.createdAt >= :from')
                ->setParameter('from', $from);
        }

        if ($to !== null) {
            $qb->andWhere('se.createdAt <= :to')
                ->setParameter('to', $to);
        }

        /** @var array<int, array{payload: array}> $results */
        $results = $qb->getQuery()->getResult();

        $subcategoryCounts = [];
        $total = 0;

        foreach ($results as $result) {
            $payload = $result['payload'];

            if (!isset($payload['ai_classification']['subcategory'])) {
                continue;
            }

            $subcategory = $payload['ai_classification']['subcategory'];
            $subcategoryCounts[$subcategory] = ($subcategoryCounts[$subcategory] ?? 0) + 1;
            $total++;
        }

        if ($total === 0) {
            return [];
        }

        arsort($subcategoryCounts);

        $patterns = [];
        foreach ($subcategoryCounts as $subcategory => $count) {
            $patterns[] = [
                'subcategory' => $subcategory,
                'count' => $count,
                'percentage' => round(($count / $total) * 100, 1),
            ];
        }

        return $patterns;
    }

    /**
     * Obtiene las subcategorias mas frecuentes.
     *
     * @return array<int, array{subcategory: string, count: int, percentage: float}>
     */
    public function getTopSubcategories(int $limit = 5): array
    {
        $patterns = $this->getPatterns();

        return array_slice($patterns, 0, $limit);
    }
}
