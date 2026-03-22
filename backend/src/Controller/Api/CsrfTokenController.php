<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class CsrfTokenController extends AbstractController
{
    #[Route('/api/csrf-token/{intention}', name: 'api_csrf_token', methods: ['GET'])]
    public function __invoke(string $intention, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        return $this->json([
            'token' => $csrfTokenManager->getToken($intention)->getValue(),
        ]);
    }
}
