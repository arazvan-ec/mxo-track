<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
class NavigationController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/api/navigation', name: 'api_navigation', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $role = $user->getRoles()[0] ?? 'ROLE_USER';

        $sections = match ($role) {
            'ROLE_ADMIN' => $this->getAdminSections(),
            'ROLE_CUSTOMER' => $this->getCustomerSections(),
            'ROLE_DRIVER' => $this->getDriverSections(),
            default => $this->getAdminSections(),
        };

        $response = new JsonResponse(['sections' => $sections]);
        $response->headers->set('Cache-Control', 'private, max-age=3600');

        return $response;
    }

    /** @return array<int, array{title: string, items: array<int, array{label: string, href: string, icon: string}>}> */
    private function getAdminSections(): array
    {
        return [
            [
                'title' => $this->t('sidebar.main'),
                'items' => [
                    $this->item('nav.dashboard', '/admin', 'dashboard'),
                    $this->item('nav.dashboard_live', '/app/admin/operator-dashboard', 'dashboardLive'),
                ],
            ],
            [
                'title' => $this->t('sidebar.operations'),
                'items' => [
                    $this->item('nav.vehicles', '/admin/vehicles', 'vehicle'),
                    $this->item('nav.drivers', '/admin/drivers', 'driver'),
                    $this->item('nav.routes', '/admin/routes', 'route'),
                    $this->item('nav.shipments', '/admin/shipments', 'shipment'),
                    $this->item('nav.planner', '/app/admin/route-planner', 'planner'),
                    $this->item('nav.route_templates', '/admin/route-templates', 'template'),
                    $this->item('nav.customers', '/admin/customers', 'customer'),
                    $this->item('nav.integrations', '/admin/integrations', 'integration'),
                ],
            ],
            [
                'title' => $this->t('sidebar.administration'),
                'items' => [
                    $this->item('nav.users', '/admin/users', 'users'),
                    $this->item('nav.reports', '/admin/reports', 'reports'),
                    $this->item('nav.sla', '/admin/reports/sla', 'sla'),
                    $this->item('nav.exception_map', '/app/admin/exception-map', 'map'),
                    $this->item('nav.billing', '/admin/billing', 'billing'),
                ],
            ],
            [
                'title' => $this->t('sidebar.tracking'),
                'items' => [
                    $this->item('nav.map', '/app/admin/fleet-map', 'map'),
                    $this->item('nav.test_routing', '/admin/test-routing', 'route'),
                ],
            ],
        ];
    }

    /** @return array<int, array{title: string, items: array<int, array{label: string, href: string, icon: string}>}> */
    private function getCustomerSections(): array
    {
        return [
            [
                'title' => $this->t('sidebar.main'),
                'items' => [
                    $this->item('nav.dashboard', '/customer', 'dashboard'),
                ],
            ],
            [
                'title' => $this->t('sidebar.my_deliveries'),
                'items' => [
                    $this->item('sidebar.my_routes', '/customer/routes', 'route'),
                    $this->item('sidebar.my_shipments', '/customer/shipments', 'shipment'),
                    $this->item('sidebar.my_reports', '/customer/reports', 'reports'),
                ],
            ],
            [
                'title' => $this->t('sidebar.tracking'),
                'items' => [
                    $this->item('nav.map', '/app/admin/fleet-map', 'map'),
                ],
            ],
        ];
    }

    /** @return array<int, array{title: string, items: array<int, array{label: string, href: string, icon: string}>}> */
    private function getDriverSections(): array
    {
        return [
            [
                'title' => $this->t('sidebar.driver'),
                'items' => [
                    $this->item('sidebar.my_routes', '/driver/routes', 'route'),
                ],
            ],
        ];
    }

    /** @return array{label: string, href: string, icon: string} */
    private function item(string $translationKey, string $href, string $icon): array
    {
        return [
            'label' => $this->t($translationKey),
            'href' => $href,
            'icon' => $icon,
        ];
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key);
    }
}
