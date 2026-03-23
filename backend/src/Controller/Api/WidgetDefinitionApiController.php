<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\WidgetDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/widgets')]
class WidgetDefinitionApiController extends AbstractController
{
    #[Route('', name: 'api_admin_widgets_list', methods: ['GET'])]
    public function list(WidgetDefinitionRepository $repo): JsonResponse
    {
        $widgets = $repo->findBy([], ['label' => 'ASC']);

        return $this->json(array_map(static fn ($w) => [
            'publicId' => $w->getPublicIdString(),
            'type' => $w->getType()->value,
            'label' => $w->getLabel(),
            'description' => $w->getDescription(),
            'previewImage' => $w->getPreviewImage(),
            'active' => $w->isActive(),
        ], $widgets));
    }

    #[Route('/{publicId}', name: 'api_admin_widgets_patch', methods: ['PATCH'])]
    public function patch(
        string $publicId,
        Request $request,
        WidgetDefinitionRepository $repo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $widget = $repo->findOneByPublicId($publicId);
        if ($widget === null) {
            return $this->json(['error' => 'Widget not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['active'])) {
            $widget->setActive((bool) $data['active']);
        }

        $em->flush();

        return $this->json([
            'publicId' => $widget->getPublicIdString(),
            'type' => $widget->getType()->value,
            'active' => $widget->isActive(),
        ]);
    }
}
