<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Route;
use App\Entity\RouteStop;
use App\Enum\RouteStopStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Predicts delivery failure risk by combining ML model scores
 * with address-based risk signals.
 */
final class DeliveryRiskService
{
    /** Boost added to the ML score when address is flagged as risky. */
    private const float ADDRESS_RISK_BOOST = 0.15;

    public function __construct(
        private readonly MlApiClient $mlClient,
        private readonly AddressRiskService $addressRiskService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Predict delivery failure risk for a single route stop.
     *
     * @return array{risk_score: float, risk_level: string, address_risk: bool}
     */
    public function predictRisk(RouteStop $stop): array
    {
        $route = $stop->getRoute();
        $startAt = $route->getStartAt();

        // Extract features from stop + route
        $features = [
            'hour_of_day' => $startAt !== null ? (int) $startAt->format('G') : 10,
            'day_of_week' => $startAt !== null ? (int) $startAt->format('N') - 1 : 0,
            'has_phone' => $stop->getRecipientPhone() !== null && $stop->getRecipientPhone() !== '',
            'parcel_count' => 1,
            'weight_kg' => 0.0,
            'stop_sequence' => $stop->getSequence(),
        ];

        // Call ML sidecar
        $mlResult = $this->mlClient->predict('predict/delivery-risk', $features);

        $score = 0.0;
        $modelVersion = 'fallback';

        if ($mlResult !== null) {
            $score = (float) ($mlResult['risk_score'] ?? 0.0);
            $modelVersion = (string) ($mlResult['model_version'] ?? 'fallback');
        }

        // Check address risk (returns ?AddressRisk entity)
        $addressRiskEntity = $this->addressRiskService->checkAddress($stop->getAddress());
        $isAddressRisky = $addressRiskEntity !== null && $addressRiskEntity->isHighRisk();

        // Boost score if address is flagged as risky
        if ($isAddressRisky) {
            $score = min(1.0, $score + self::ADDRESS_RISK_BOOST);
        }

        $riskLevel = match (true) {
            $score < 0.2 => 'LOW',
            $score <= 0.5 => 'MEDIUM',
            default => 'HIGH',
        };

        $this->logger->debug('Delivery risk predicted', [
            'stop_sequence' => $stop->getSequence(),
            'risk_score' => $score,
            'risk_level' => $riskLevel,
            'address_risk' => $isAddressRisky,
            'model_version' => $modelVersion,
        ]);

        return [
            'risk_score' => round($score, 4),
            'risk_level' => $riskLevel,
            'address_risk' => $isAddressRisky,
        ];
    }

    /**
     * Predict risk for all non-origin stops on a route.
     *
     * @return array<int, array{risk_score: float, risk_level: string, address_risk: bool}>
     *     Keyed by stop sequence number.
     */
    public function getRiskScoresForRoute(Route $route, EntityManagerInterface $em): array
    {
        /** @var list<RouteStop> $stops */
        $stops = $em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->andWhere('s.isOrigin = false')
            ->andWhere('s.status = :pending')
            ->setParameter('route', $route)
            ->setParameter('pending', RouteStopStatus::PENDING)
            ->orderBy('s.sequence', 'ASC')
            ->getQuery()
            ->getResult();

        $scores = [];

        foreach ($stops as $stop) {
            $scores[$stop->getSequence()] = $this->predictRisk($stop);
        }

        return $scores;
    }
}
