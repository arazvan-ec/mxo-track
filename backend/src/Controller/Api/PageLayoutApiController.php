<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\PageLayout;
use App\Entity\PageLayoutWidget;
use App\Enum\PageKey;
use App\Enum\SheetState;
use App\Enum\WidgetType;
use App\Repository\PageLayoutRepository;
use App\Repository\WidgetDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/page-layouts')]
class PageLayoutApiController extends AbstractController
{
    #[Route('', name: 'api_admin_page_layouts_list', methods: ['GET'])]
    public function list(Request $request, PageLayoutRepository $repo): JsonResponse
    {
        $criteria = [];

        $pageKeyParam = $request->query->get('pageKey');
        if ($pageKeyParam !== null) {
            $pageKey = PageKey::tryFrom($pageKeyParam);
            if ($pageKey === null) {
                return $this->json(['error' => 'Invalid page key'], 400);
            }
            $criteria['pageKey'] = $pageKey;
        }

        $layouts = $repo->findBy($criteria, ['createdAt' => 'ASC']);

        return $this->json(array_map(fn (PageLayout $l) => $this->serializeLayout($l), $layouts));
    }

    #[Route('/{publicId}', name: 'api_admin_page_layouts_get', methods: ['GET'])]
    public function get(string $publicId, PageLayoutRepository $repo): JsonResponse
    {
        $layout = $repo->findOneByPublicId($publicId);
        if ($layout === null) {
            return $this->json(['error' => 'Layout not found'], 404);
        }

        return $this->json($this->serializeLayoutWithWidgets($layout));
    }

    #[Route('', name: 'api_admin_page_layouts_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        WidgetDefinitionRepository $widgetRepo,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $pageKey = PageKey::tryFrom($data['pageKey'] ?? '');
        if ($pageKey === null) {
            return $this->json(['error' => 'Invalid page key'], 400);
        }

        $layout = new PageLayout($pageKey);
        $this->applyWidgets($layout, $data['widgets'] ?? [], $widgetRepo);

        $em->persist($layout);
        $em->flush();

        return $this->json($this->serializeLayoutWithWidgets($layout), 201);
    }

    #[Route('/{publicId}', name: 'api_admin_page_layouts_update', methods: ['PUT'])]
    public function update(
        string $publicId,
        Request $request,
        PageLayoutRepository $repo,
        EntityManagerInterface $em,
        WidgetDefinitionRepository $widgetRepo,
    ): JsonResponse {
        $layout = $repo->findOneByPublicId($publicId);
        if ($layout === null) {
            return $this->json(['error' => 'Layout not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        $layout->clearWidgets();
        $this->applyWidgets($layout, $data['widgets'] ?? [], $widgetRepo);

        $em->flush();

        return $this->json($this->serializeLayoutWithWidgets($layout));
    }

    #[Route('/{publicId}', name: 'api_admin_page_layouts_delete', methods: ['DELETE'])]
    public function delete(
        string $publicId,
        PageLayoutRepository $repo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $layout = $repo->findOneByPublicId($publicId);
        if ($layout === null) {
            return $this->json(['error' => 'Layout not found'], 404);
        }

        $em->remove($layout);
        $em->flush();

        return $this->json(null, 204);
    }

    /**
     * @param array<string, list<array{type: string}>> $widgetsData
     */
    private function applyWidgets(PageLayout $layout, array $widgetsData, WidgetDefinitionRepository $widgetRepo): void
    {
        foreach (SheetState::cases() as $state) {
            $items = $widgetsData[$state->value] ?? [];
            foreach ($items as $position => $item) {
                $widgetType = WidgetType::tryFrom($item['type'] ?? '');
                if ($widgetType === null) {
                    continue;
                }
                $widgetDef = $widgetRepo->findByType($widgetType);
                if ($widgetDef === null) {
                    continue;
                }
                $plw = new PageLayoutWidget($layout, $widgetDef, $state, $position);
                $layout->addWidget($plw);
            }
        }
    }

    /** @return array<string, mixed> */
    private function serializeLayout(PageLayout $layout): array
    {
        return [
            'publicId' => $layout->getPublicIdString(),
            'pageKey' => $layout->getPageKey()->value,
            'customerId' => $layout->getCustomer()?->getPublicIdString(),
            'active' => $layout->isActive(),
            'createdAt' => $layout->getCreatedAt()->format('c'),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeLayoutWithWidgets(PageLayout $layout): array
    {
        $base = $this->serializeLayout($layout);

        $widgets = [
            'collapsed' => [],
            'half' => [],
            'full' => [],
        ];

        foreach (SheetState::cases() as $state) {
            foreach ($layout->getWidgetsForState($state) as $plw) {
                $widgets[$state->value][] = [
                    'type' => $plw->getWidgetDefinition()->getType()->value,
                    'position' => $plw->getPosition(),
                ];
            }
        }

        $base['widgets'] = $widgets;

        return $base;
    }
}
