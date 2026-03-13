<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Enum\OptimizationOperation;
use App\Repository\RouteOptimizationLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[SymfonyRoute('/admin/optimization-logs')]
#[IsGranted('ROLE_ADMIN')]
class OptimizationLogController extends AbstractController
{
    public function __construct(
        private readonly RouteOptimizationLogRepository $logRepo,
    ) {
    }

    #[SymfonyRoute('', name: 'admin_optimization_logs_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $operationFilter = $request->query->getString('operation', '');
        $operation = OptimizationOperation::tryFrom($operationFilter);

        $logs = $operation !== null
            ? $this->logRepo->findByOperation($operation, 50)
            : $this->logRepo->findRecent(50);

        return $this->render('admin/optimization_log/index.html.twig', [
            'logs' => $logs,
            'operations' => OptimizationOperation::cases(),
            'currentOperation' => $operationFilter,
        ]);
    }

    #[SymfonyRoute('/{publicId}', name: 'admin_optimization_logs_show', methods: ['GET'])]
    public function show(string $publicId): Response
    {
        $log = $this->logRepo->findOneByPublicId($publicId);

        if ($log === null) {
            throw $this->createNotFoundException('Log not found.');
        }

        return $this->render('admin/optimization_log/show.html.twig', [
            'log' => $log,
        ]);
    }
}
