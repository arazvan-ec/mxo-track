<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[SymfonyRoute('/admin/route-planner')]
#[IsGranted('ROLE_ADMIN')]
class RoutePlannerController extends AbstractController
{
    #[SymfonyRoute('', name: 'admin_route_planner_index', methods: ['GET'])]
    public function index(): Response
    {
        return new Response('Route planner index - TODO');
    }

    #[SymfonyRoute('/shipments', name: 'admin_route_planner_shipments', methods: ['GET'])]
    public function shipments(): JsonResponse
    {
        return new JsonResponse(['shipments' => []]);
    }

    #[SymfonyRoute('/preview', name: 'admin_route_planner_preview', methods: ['POST'])]
    public function preview(): JsonResponse
    {
        return new JsonResponse(['routes' => []]);
    }

    #[SymfonyRoute('/confirm', name: 'admin_route_planner_confirm', methods: ['POST'])]
    public function confirm(): Response
    {
        return $this->redirectToRoute('admin_routes_index');
    }
}
