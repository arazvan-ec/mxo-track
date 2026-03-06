<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Route as RouteEntity;
use App\Http\ApiErrorResponder;
use App\Repository\RouteRepository;
use App\Service\LoadingManifestGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_OPERATOR')]
class LoadingManifestApiController extends AbstractController
{
    #[Route('/api/routes/{publicId}/loading-manifest', name: 'api_routes_loading_manifest', methods: ['GET'])]
    public function loadingManifest(
        string $publicId,
        RouteRepository $routeRepository,
        LoadingManifestGenerator $manifestGenerator,
        ApiErrorResponder $errorResponder,
    ): JsonResponse {
        $route = $routeRepository->findOneByPublicId($publicId);

        if (!$route instanceof RouteEntity) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        $manifest = $manifestGenerator->generateManifest($route);

        $items = [];
        foreach ($manifest as $item) {
            $items[] = [
                'loading_order' => $item->loadingOrder,
                'delivery_sequence' => $item->deliverySequence,
                'shipment_public_id' => $item->shipmentPublicId,
                'shipment_reference' => $item->shipmentReference,
                'recipient_name' => $item->recipientName,
                'address' => $item->address,
                'recipient_phone' => $item->recipientPhone,
                'weight_kg' => $item->weightKg,
                'volume_m3' => $item->volumeM3,
                'parcels' => $item->parcels,
            ];
        }

        return $this->json(['items' => $items]);
    }
}
