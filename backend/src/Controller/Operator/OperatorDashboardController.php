<?php

declare(strict_types=1);

namespace App\Controller\Operator;

use App\Service\AdminMetricsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/operator')]
#[IsGranted('ROLE_ADMIN')]
final class OperatorDashboardController extends AbstractController
{
    public function __construct(
        private readonly AdminMetricsService $metricsService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'operator_dashboard')]
    public function dashboard(): Response
    {
        $metrics = $this->metricsService->collect();

        return $this->render('operator/dashboard.html.twig', [
            'metrics' => $metrics,
        ]);
    }
}
