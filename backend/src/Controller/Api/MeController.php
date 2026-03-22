<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'publicId' => $user->getPublicIdString(),
            'email' => $user->getEmail(),
            'role' => $user->getRoles()[0] ?? 'ROLE_USER',
            'customerId' => $user->getCustomer()?->getPublicIdString(),
            'customerName' => $user->getCustomer()?->getName(),
            'locale' => $request->getLocale(),
        ]);
    }
}
