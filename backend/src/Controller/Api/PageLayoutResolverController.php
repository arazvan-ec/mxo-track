<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\PageLayout;
use App\Entity\User;
use App\Enum\PageKey;
use App\Enum\SheetState;
use App\Repository\PageLayoutRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class PageLayoutResolverController extends AbstractController
{
    #[Route('/api/page-layouts/{pageKey}', name: 'api_page_layout_resolve', methods: ['GET'])]
    public function __invoke(string $pageKey, PageLayoutRepository $repo): JsonResponse
    {
        $pageKeyEnum = PageKey::tryFrom($pageKey);
        if ($pageKeyEnum === null) {
            return $this->json(['error' => 'Invalid page key'], 404);
        }

        /** @var User $user */
        $user = $this->getUser();
        $customer = $user->getCustomer();

        $layout = $repo->findForPage($pageKeyEnum, $customer);

        if ($layout === null) {
            return $this->json([
                'pageKey' => $pageKey,
                'scope' => 'none',
                'widgets' => [
                    'collapsed' => [],
                    'half' => [],
                    'full' => [],
                ],
            ]);
        }

        return $this->json([
            'pageKey' => $pageKey,
            'scope' => $layout->getCustomer() !== null ? 'customer' : 'global',
            'widgets' => $this->serializeLayoutWidgets($layout),
        ]);
    }

    /** @return array<string, list<array{type: string, position: int}>> */
    private function serializeLayoutWidgets(PageLayout $layout): array
    {
        $result = [
            'collapsed' => [],
            'half' => [],
            'full' => [],
        ];

        foreach (SheetState::cases() as $state) {
            $widgets = $layout->getWidgetsForState($state);
            foreach ($widgets as $plw) {
                $result[$state->value][] = [
                    'type' => $plw->getWidgetDefinition()->getType()->value,
                    'position' => $plw->getPosition(),
                ];
            }
        }

        return $result;
    }
}
