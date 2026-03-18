<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[SymfonyRoute('/admin/routes')]
#[IsGranted('ROLE_ADMIN')]
class RouteAnalysisController extends AbstractController
{
    #[SymfonyRoute('/{publicId}/analysis', name: 'admin_routes_analysis', methods: ['GET'])]
    public function analysis(string $publicId): Response
    {
        return $this->redirect('/app/admin/routes/' . $publicId . '/analysis');
    }
}
