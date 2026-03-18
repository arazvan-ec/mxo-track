<?php

declare(strict_types=1);

namespace App\Infrastructure\MapView\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SpaController extends AbstractController
{
    #[Route('/app/{path}', name: 'spa_entrypoint', requirements: ['path' => '.*'], methods: ['GET'])]
    public function __invoke(): Response
    {
        $indexPath = $this->getParameter('kernel.project_dir') . '/public/app/index.html';

        if (!file_exists($indexPath)) {
            throw $this->createNotFoundException('SPA not built. Run: cd frontend && npm run build');
        }

        return new Response(file_get_contents($indexPath), 200, [
            'Content-Type' => 'text/html',
        ]);
    }
}
