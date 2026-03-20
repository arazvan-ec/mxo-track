<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Route\Model\Route;
use App\Domain\Shipment\Model\Shipment;
use App\Entity\User;
use App\Entity\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SearchService
{
    private const int MAX_RESULTS_PER_TYPE = 10;
    private const float SEMANTIC_MIN_SIMILARITY = 0.7;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EmbeddingService $embeddingService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array<array{type: string, label: string, url: string, extra: string}>
     */
    public function search(string $query, ?User $user): array
    {
        $query = trim($query);
        if ($query === '' || mb_strlen($query) < 2) {
            return [];
        }

        $results = [];
        $isAdmin = $user !== null && $user->hasRole('ROLE_ADMIN');
        $isCustomer = $user !== null && $user->hasRole('ROLE_CUSTOMER') && !$isAdmin;
        $customer = $user?->getCustomer();

        // Keyword search (SQL LIKE)
        $results = array_merge($results, $this->searchShipments($query, $isCustomer, $customer));
        $results = array_merge($results, $this->searchRoutes($query, $isAdmin, $isCustomer, $customer));

        if ($isAdmin) {
            $results = array_merge($results, $this->searchVehicles($query));
        }

        // Semantic search: complement with vector similarity when keyword results are sparse
        if (\count($results) < 3) {
            $semanticResults = $this->searchSemantic($query, $isCustomer, $customer);
            $results = $this->mergeDeduplicateResults($results, $semanticResults);
        }

        return $results;
    }

    /**
     * @return array<array{type: string, label: string, url: string, extra: string}>
     */
    private function searchShipments(string $query, bool $isCustomer, ?\App\Entity\Customer $customer): array
    {
        $pattern = '%' . mb_strtolower($query) . '%';

        // Shipment is CustomerScoped, so if tenant filter is active it auto-filters.
        // But for search service, we build our own query for clarity.
        $qb = $this->em->createQueryBuilder()
            ->select('s')
            ->from(Shipment::class, 's')
            ->where('LOWER(s.reference) LIKE :pattern')
            ->orWhere('LOWER(s.recipientName) LIKE :pattern')
            ->setParameter('pattern', $pattern)
            ->setMaxResults(self::MAX_RESULTS_PER_TYPE)
            ->orderBy('s.createdAt', 'DESC');

        if ($isCustomer && $customer !== null) {
            $qb->andWhere('s.customer = :customer')
                ->setParameter('customer', $customer);
        }

        $shipments = $qb->getQuery()->getResult();
        $results = [];

        foreach ($shipments as $shipment) {
            $url = $isCustomer
                ? $this->urlGenerator->generate('customer_shipments_show', ['publicId' => $shipment->getPublicIdString()])
                : $this->urlGenerator->generate('customer_shipments_show', ['publicId' => $shipment->getPublicIdString()]);

            $results[] = [
                'type' => 'shipment',
                'label' => $shipment->getReference(),
                'url' => $url,
                'extra' => $shipment->getRecipientName() ?? '',
            ];
        }

        return $results;
    }

    /**
     * @return array<array{type: string, label: string, url: string, extra: string}>
     */
    private function searchRoutes(string $query, bool $isAdmin, bool $isCustomer, ?\App\Entity\Customer $customer): array
    {
        $pattern = '%' . mb_strtolower($query) . '%';

        $qb = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Route::class, 'r')
            ->where('LOWER(r.name) LIKE :pattern')
            ->setParameter('pattern', $pattern)
            ->setMaxResults(self::MAX_RESULTS_PER_TYPE)
            ->orderBy('r.id', 'DESC');

        if ($isCustomer && $customer !== null) {
            $qb->andWhere('r.customer = :customer')
                ->setParameter('customer', $customer);
        }

        $routes = $qb->getQuery()->getResult();
        $results = [];

        foreach ($routes as $route) {
            $url = $isAdmin
                ? $this->urlGenerator->generate('admin_routes_edit', ['publicId' => $route->getPublicIdString()])
                : $this->urlGenerator->generate('customer_routes_show', ['publicId' => $route->getPublicIdString()]);

            $results[] = [
                'type' => 'route',
                'label' => $route->getName(),
                'url' => $url,
                'extra' => $route->getStatus()->value,
            ];
        }

        return $results;
    }

    /**
     * @return array<array{type: string, label: string, url: string, extra: string}>
     */
    private function searchVehicles(string $query): array
    {
        $pattern = '%' . mb_strtolower($query) . '%';

        $vehicles = $this->em->createQueryBuilder()
            ->select('v')
            ->from(Vehicle::class, 'v')
            ->where('LOWER(v.name) LIKE :pattern')
            ->setParameter('pattern', $pattern)
            ->setMaxResults(self::MAX_RESULTS_PER_TYPE)
            ->orderBy('v.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $results = [];

        foreach ($vehicles as $vehicle) {
            $results[] = [
                'type' => 'vehicle',
                'label' => $vehicle->getName(),
                'url' => $this->urlGenerator->generate('admin_vehicles_edit', ['publicId' => $vehicle->getPublicIdString()]),
                'extra' => $vehicle->isActive() ? 'Activo' : 'Inactivo',
            ];
        }

        return $results;
    }

    /**
     * @return array<array{type: string, label: string, url: string, extra: string}>
     */
    private function searchSemantic(string $query, bool $isCustomer, ?\App\Entity\Customer $customer): array
    {
        try {
            $matches = $this->embeddingService->search($query, 'shipment', self::MAX_RESULTS_PER_TYPE);
        } catch (\Throwable $e) {
            $this->logger->debug('Semantic search unavailable: {error}', ['error' => $e->getMessage()]);

            return [];
        }

        $results = [];
        foreach ($matches as $match) {
            if ($match['similarity'] < self::SEMANTIC_MIN_SIMILARITY) {
                continue;
            }

            $shipment = $this->em->getRepository(Shipment::class)->find($match['entity_id']);
            if ($shipment === null) {
                continue;
            }

            if ($isCustomer && $customer !== null && $shipment->getCustomer()?->getId() !== $customer->getId()) {
                continue;
            }

            $results[] = [
                'type' => 'shipment',
                'label' => $shipment->getReference(),
                'url' => $this->urlGenerator->generate('customer_shipments_show', ['publicId' => $shipment->getPublicIdString()]),
                'extra' => $shipment->getRecipientName() ?? '',
            ];
        }

        return $results;
    }

    /**
     * @param array<array{type: string, label: string, url: string, extra: string}> $existing
     * @param array<array{type: string, label: string, url: string, extra: string}> $additional
     * @return array<array{type: string, label: string, url: string, extra: string}>
     */
    private function mergeDeduplicateResults(array $existing, array $additional): array
    {
        $seen = [];
        foreach ($existing as $result) {
            $seen[$result['type'] . ':' . $result['label']] = true;
        }

        foreach ($additional as $result) {
            $key = $result['type'] . ':' . $result['label'];
            if (!isset($seen[$key])) {
                $existing[] = $result;
                $seen[$key] = true;
            }
        }

        return $existing;
    }
}
