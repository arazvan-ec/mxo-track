<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\MercureJwtFactory;
use App\Service\VisibilityScopeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Ulid;

class MercureTokenController extends AbstractController
{
    #[Route('/api/mercure-token', name: 'api_mercure_token', methods: ['GET'])]
    public function __invoke(
        Request $request,
        MercureJwtFactory $factory,
        VisibilityScopeService $visibilityScopeService,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $requestedVehiclePublicIds = $request->query->all('vehicle_ids');
        $normalizedVehiclePublicIds = [];

        $invalidVehicleIds = [];
        foreach ($requestedVehiclePublicIds as $candidate) {
            $value = trim((string) $candidate);
            if ($value === '') {
                $invalidVehicleIds[] = (string) $candidate;
                continue;
            }

            try {
                Ulid::fromString($value);
                $normalizedVehiclePublicIds[] = $value;
            } catch (\Throwable) {
                $invalidVehicleIds[] = $value;
            }
        }

        if ($invalidVehicleIds !== []) {
            return $this->json([
                'error' => 'validation_error',
                'message' => 'Todos los vehicle_ids deben ser ULID válidos.',
                'invalid_vehicle_ids' => array_values(array_unique($invalidVehicleIds)),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $authorizedVehiclePublicIds = $visibilityScopeService->vehiclePublicIdsFor($user);
        if ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_OPERATOR')) {
            $effectiveVehiclePublicIds = array_values(array_unique($normalizedVehiclePublicIds));
        } elseif ($normalizedVehiclePublicIds === []) {
            $effectiveVehiclePublicIds = $authorizedVehiclePublicIds;
        } else {
            $effectiveVehiclePublicIds = array_values(array_intersect(
                $authorizedVehiclePublicIds,
                array_values(array_unique($normalizedVehiclePublicIds)),
            ));
        }

        $token = $factory->createSubscriberToken($user, $effectiveVehiclePublicIds);

        $response = new JsonResponse(['ok' => true]);
        $response->headers->setCookie(Cookie::create('mercureAuthorization')
            ->withValue('Bearer '.$token)
            ->withHttpOnly(true)
            ->withSecure($request->isSecure() || 'prod' === ($_ENV['APP_ENV'] ?? null))
            ->withSameSite('lax')
            ->withPath('/.well-known/mercure'));

        return $response;
    }
}
