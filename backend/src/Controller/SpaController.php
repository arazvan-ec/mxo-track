<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class SpaController
{
    #[Route('/app/{path}', name: 'spa_catchall', requirements: ['path' => '.+'], methods: ['GET'], priority: -100)]
    public function __invoke(string $path = ''): Response
    {
        $indexPath = \dirname(__DIR__, 2) . '/public/app/index.html';

        if (!file_exists($indexPath)) {
            return new Response('SPA not built. Run: cd frontend && npm run build', 404);
        }

        return new Response(
            file_get_contents($indexPath),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }
}
