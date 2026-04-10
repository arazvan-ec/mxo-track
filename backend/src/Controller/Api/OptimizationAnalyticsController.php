<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Route\Model\RouteEvent;
use App\Entity\AddressRisk;
use App\Enum\RouteEventType;
use App\Repository\AddressRiskRepository;
use App\Repository\RoutePerformanceMetricRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_OPERATOR')]
#[Route('/api/admin/optimization')]
final class OptimizationAnalyticsController
{
    public function __construct(
        private readonly RoutePerformanceMetricRepository $metricsRepo,
        private readonly AddressRiskRepository $addressRiskRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/metrics', name: 'api_admin_optimization_metrics', methods: ['GET'])]
    public function metrics(): JsonResponse
    {
        $since = new \DateTimeImmutable('-90 days');
        $rows = $this->metricsRepo->getMetricsByOptimizer($since);

        $result = array_map(static fn (array $row): array => [
            'optimizer_name' => $row['optimizer_used'],
            'avg_distance_km' => $row['avg_distance_km'],
            'avg_duration_min' => $row['avg_duration_min'],
            'route_count' => $row['route_count'],
            'avg_success_rate' => $row['avg_success_rate'],
        ], $rows);

        return new JsonResponse($result);
    }

    #[Route('/address-risks', name: 'api_admin_optimization_address_risks', methods: ['GET'])]
    public function addressRisks(): JsonResponse
    {
        /** @var list<AddressRisk> $risks */
        $risks = $this->addressRiskRepo->createQueryBuilder('a')
            ->where('a.totalDeliveries > :minDeliveries')
            ->setParameter('minDeliveries', 5)
            ->orderBy('a.exceptionRate', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $result = array_map(static fn (AddressRisk $risk): array => [
            'address' => $risk->getAddress(),
            'total_deliveries' => $risk->getTotalDeliveries(),
            'exception_count' => $risk->getExceptionCount(),
            'exception_rate' => $risk->getExceptionRate(),
            'is_high_risk' => $risk->isHighRisk(),
        ], $risks);

        return new JsonResponse($result);
    }

    #[Route('/reopt-history', name: 'api_admin_optimization_reopt_history', methods: ['GET'])]
    public function reoptHistory(): JsonResponse
    {
        /** @var list<RouteEvent> $events */
        $events = $this->em->createQueryBuilder()
            ->select('e', 'r')
            ->from(RouteEvent::class, 'e')
            ->join('e.route', 'r')
            ->where('e.eventType = :type')
            ->setParameter('type', RouteEventType::REOPTIMIZED->value)
            ->orderBy('e.occurredAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        $result = array_map(static fn (RouteEvent $event): array => [
            'route_public_id' => $event->getRoute()->getPublicIdString(),
            'trigger' => $event->getPayload()['trigger'] ?? $event->getActorType(),
            'occurred_at' => $event->getOccurredAt()->format(\DateTimeInterface::ATOM),
        ], $events);

        return new JsonResponse($result);
    }
}
