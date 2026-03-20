<?php

declare(strict_types=1);

namespace App\Controller\Customer;

use App\Application\Fleet\FleetOverviewService;
use App\Entity\Customer;
use App\Entity\CustomerVehicle;
use App\Domain\Route\Model\Route;
use App\Entity\VehicleLastPosition;
use App\Enum\RouteStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[SymfonyRoute('/customer')]
#[IsGranted('ROLE_CUSTOMER')]
class CustomerDashboardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FleetOverviewService $fleetOverview,
        #[Autowire('%env(MERCURE_PUBLIC_URL)%')] private readonly string $mercurePublicUrl,
    ) {}

    #[SymfonyRoute('/dashboard', name: 'customer_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $customer = $this->resolveCustomer();

        $kpis = $this->fleetOverview->getCustomerKpis($customer);
        $activeRouteProgress = $this->fleetOverview->getActiveRoutesProgress($customer);

        // Active routes with vehicle + driver (last 5)
        $activeRoutesList = $this->em->createQueryBuilder()
            ->select('r', 'v', 'd')
            ->from(Route::class, 'r')
            ->leftJoin('r.vehicle', 'v')
            ->leftJoin('r.driver', 'd')
            ->where('r.customer = :customer')
            ->andWhere('r.status = :active')
            ->setParameter('customer', $customer)
            ->setParameter('active', RouteStatus::ACTIVE)
            ->orderBy('r.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Customer vehicles with last position
        $vehiclesWithPosition = $this->em->createQueryBuilder()
            ->select('cv', 'v', 'vlp')
            ->from(CustomerVehicle::class, 'cv')
            ->join('cv.vehicle', 'v')
            ->leftJoin(VehicleLastPosition::class, 'vlp', 'WITH', 'vlp.vehicle = v')
            ->where('cv.customer = :customer')
            ->andWhere('v.isActive = true')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getResult();

        return $this->render('customer/dashboard.html.twig', [
            'customer' => $customer,
            'kpis' => $kpis->toArray(),
            'activeRoutes' => $activeRoutesList,
            'activeRouteProgress' => $activeRouteProgress,
            'vehiclesWithPosition' => $vehiclesWithPosition,
            'mercure_public_url' => $this->mercurePublicUrl,
        ]);
    }

    #[SymfonyRoute('/dashboard/kpis', name: 'customer_dashboard_kpis', methods: ['GET'])]
    public function kpis(): JsonResponse
    {
        $customer = $this->resolveCustomer();

        return $this->json($this->fleetOverview->getCustomerKpis($customer)->toArray());
    }

    private function resolveCustomer(): Customer
    {
        $user = $this->getUser();
        $customer = $user->getCustomer();

        if (!$customer instanceof Customer) {
            throw $this->createAccessDeniedException('No tiene un cliente asociado.');
        }

        return $customer;
    }
}
