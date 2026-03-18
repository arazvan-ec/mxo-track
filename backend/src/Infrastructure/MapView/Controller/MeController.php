<?php

declare(strict_types=1);

namespace App\Infrastructure\MapView\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'role' => $user->getRoles()[0] ?? 'ROLE_USER',
            'customerId' => $user->getCustomer()?->getPublicIdString(),
            'customerName' => $user->getCustomer()?->getName(),
        ]);
    }
}
