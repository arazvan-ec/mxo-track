<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\MercureJwtFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MercureTokenController extends AbstractController
{
    #[Route('/api/mercure-token', name: 'api_mercure_token', methods: ['GET'])]
    public function __invoke(Request $request, MercureJwtFactory $factory): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $allowedVehicleIds = $request->query->all('vehicle_ids');
        $token = $factory->createSubscriberToken($user, $allowedVehicleIds);

        $response = new JsonResponse(['ok' => true]);
        $response->headers->setCookie(Cookie::create('mercureAuthorization')
            ->withValue('Bearer '.$token)
            ->withHttpOnly(true)
            ->withSecure('prod' === $_ENV['APP_ENV'])
            ->withSameSite('lax')
            ->withPath('/.well-known/mercure'));

        return $response;
    }
}
