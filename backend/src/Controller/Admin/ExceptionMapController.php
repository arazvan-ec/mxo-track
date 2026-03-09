<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Customer;
use App\Entity\RouteStop;
use App\Enum\ExceptionCode;
use App\Enum\RouteStopStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports/exception-map')]
#[IsGranted('ROLE_ADMIN')]
class ExceptionMapController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'admin_reports_exception_map', methods: ['GET'])]
    public function index(): Response
    {
        $exceptionCodes = array_map(
            fn(ExceptionCode $code) => ['value' => $code->value, 'label' => $code->value],
            ExceptionCode::cases(),
        );

        $customers = $this->em->createQueryBuilder()
            ->select('c.id', 'c.name')
            ->from(Customer::class, 'c')
            ->where('c.isActive = true')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/reports/exception_map.html.twig', [
            'exception_codes' => $exceptionCodes,
            'customers' => $customers,
        ]);
    }

    #[Route('/data', name: 'admin_reports_exception_map_data', methods: ['GET'])]
    public function data(Request $request): JsonResponse
    {
        $exceptionCode = $request->query->getString('exception_code', '');
        $fromStr = $request->query->getString('from', '');
        $toStr = $request->query->getString('to', '');
        $customerId = $request->query->getInt('customer_id', 0);

        $qb = $this->em->createQueryBuilder()
            ->select(
                'ROUND(rs.latitude, 4) AS lat',
                'ROUND(rs.longitude, 4) AS lng',
                'COUNT(rs.id) AS weight',
                'rs.exceptionCode AS exceptionCode',
                'rs.address AS address',
            )
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('rs.status = :exception')
            ->andWhere('rs.isOrigin = false')
            ->andWhere('rs.latitude IS NOT NULL')
            ->andWhere('rs.longitude IS NOT NULL')
            ->setParameter('exception', RouteStopStatus::EXCEPTION)
            ->groupBy('lat, lng, rs.exceptionCode, rs.address')
            ->orderBy('weight', 'DESC');

        if ($exceptionCode !== '') {
            $qb->andWhere('rs.exceptionCode = :code')
                ->setParameter('code', $exceptionCode);
        }

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

        if ($customerId > 0) {
            $qb->andWhere('r.customer = :customerId')
                ->setParameter('customerId', $customerId);
        }

        $results = $qb->setMaxResults(5000)->getQuery()->getResult();

        $points = array_map(fn(array $row) => [
            'lat' => (float) $row['lat'],
            'lng' => (float) $row['lng'],
            'weight' => (int) $row['weight'],
            'exceptionCode' => $row['exceptionCode'] instanceof ExceptionCode
                ? $row['exceptionCode']->value
                : (string) ($row['exceptionCode'] ?? 'OTHER'),
            'address' => (string) $row['address'],
        ], $results);

        return $this->json($points);
    }

    #[Route('/top-addresses', name: 'admin_reports_exception_map_top_addresses', methods: ['GET'])]
    public function topAddresses(Request $request): JsonResponse
    {
        $exceptionCode = $request->query->getString('exception_code', '');
        $fromStr = $request->query->getString('from', '');
        $toStr = $request->query->getString('to', '');
        $customerId = $request->query->getInt('customer_id', 0);

        $qb = $this->em->createQueryBuilder()
            ->select(
                'rs.address',
                'COUNT(rs.id) AS total',
                'rs.latitude AS lat',
                'rs.longitude AS lng',
            )
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('rs.status = :exception')
            ->andWhere('rs.isOrigin = false')
            ->andWhere('rs.latitude IS NOT NULL')
            ->andWhere('rs.longitude IS NOT NULL')
            ->setParameter('exception', RouteStopStatus::EXCEPTION)
            ->groupBy('rs.address, rs.latitude, rs.longitude')
            ->orderBy('total', 'DESC')
            ->setMaxResults(10);

        if ($exceptionCode !== '') {
            $qb->andWhere('rs.exceptionCode = :code')
                ->setParameter('code', $exceptionCode);
        }

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

        if ($customerId > 0) {
            $qb->andWhere('r.customer = :customerId')
                ->setParameter('customerId', $customerId);
        }

        $results = $qb->getQuery()->getResult();

        // Get exception code breakdown per address
        $addresses = [];
        foreach ($results as $row) {
            $address = (string) $row['address'];

            $codeQb = $this->em->createQueryBuilder()
                ->select('rs.exceptionCode AS code', 'COUNT(rs.id) AS cnt')
                ->from(RouteStop::class, 'rs')
                ->join('rs.route', 'r')
                ->where('rs.status = :exception')
                ->andWhere('rs.address = :address')
                ->andWhere('rs.isOrigin = false')
                ->setParameter('exception', RouteStopStatus::EXCEPTION)
                ->setParameter('address', $address)
                ->groupBy('rs.exceptionCode');

            $codeResults = $codeQb->getQuery()->getResult();
            $codes = [];
            foreach ($codeResults as $codeRow) {
                $codeValue = $codeRow['code'] instanceof ExceptionCode
                    ? $codeRow['code']->value
                    : (string) ($codeRow['code'] ?? 'OTHER');
                $codes[] = ['code' => $codeValue, 'count' => (int) $codeRow['cnt']];
            }

            $addresses[] = [
                'address' => $address,
                'total' => (int) $row['total'],
                'lat' => (float) $row['lat'],
                'lng' => (float) $row['lng'],
                'codes' => $codes,
            ];
        }

        return $this->json($addresses);
    }
}
