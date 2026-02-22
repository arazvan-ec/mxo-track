<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class SearchController extends AbstractController
{
    public function __construct(
        private readonly SearchService $searchService,
    ) {}

    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = trim($request->query->getString('q', ''));

        /** @var User $user */
        $user = $this->getUser();

        $results = $query !== '' ? $this->searchService->search($query, $user) : [];

        return $this->render('search/results.html.twig', [
            'query' => $query,
            'results' => $results,
        ]);
    }

    #[Route('/api/search', name: 'api_search', methods: ['GET'])]
    public function apiSearch(Request $request): JsonResponse
    {
        $query = trim($request->query->getString('q', ''));

        /** @var User $user */
        $user = $this->getUser();

        $results = $query !== '' ? $this->searchService->search($query, $user) : [];

        return $this->json(['results' => $results]);
    }
}
