<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Route as RouteEntity;
use App\Http\ApiErrorResponder;
use App\Service\LoadingManifestGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class LoadingManifestApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoadingManifestGenerator $manifestGenerator,
        private readonly ApiErrorResponder $errorResponder,
    ) {}

    #[Route('/routes/{publicId}/loading-manifest', name: 'api_route_loading_manifest', methods: ['GET'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function loadingManifest(string $publicId): JsonResponse
    {
        $route = $this->em->getRepository(RouteEntity::class)
            ->findOneBy(['publicId' => $publicId]);

        if ($route === null) {
            return $this->errorResponder->notFound('Ruta no encontrada.');
        }

        $manifest = $this->manifestGenerator->generateManifest($route);

        return new JsonResponse(array_map(static fn ($item): array => [
            'loadingOrder' => $item->loadingOrder,
            'deliverySequence' => $item->deliverySequence,
            'shipmentPublicId' => $item->shipmentPublicId,
            'shipmentReference' => $item->shipmentReference,
            'recipientName' => $item->recipientName,
            'address' => $item->address,
            'recipientPhone' => $item->recipientPhone,
            'weightKg' => $item->weightKg,
            'volumeM3' => $item->volumeM3,
            'parcels' => $item->parcels,
        ], $manifest));
    }
}
