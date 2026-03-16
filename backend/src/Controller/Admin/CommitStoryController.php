<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\GitLogReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/commit-story')]
#[IsGranted('ROLE_OPERATOR')]
class CommitStoryController extends AbstractController
{
    public function __construct(
        private readonly GitLogReader $gitLogReader,
    ) {
    }

    #[Route('', name: 'admin_commit_story', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $branch = $request->query->getString('branch', '');

        if ($branch === '') {
            return $this->renderBranchSelector();
        }

        $base = $request->query->getString('base', '') ?: null;

        try {
            $commits = $this->gitLogReader->getCommits($branch, $base);
        } catch (\RuntimeException $e) {
            return $this->render('export/commit_story.html.twig', [
                'branch' => $branch,
                'commits' => [],
                'total_commits' => 0,
                'total_insertions' => 0,
                'total_deletions' => 0,
                'total_files' => 0,
                'authors' => [],
                'type_breakdown' => [],
                'date_from' => null,
                'date_to' => null,
                'generated_at' => new \DateTimeImmutable(),
                'error' => $e->getMessage(),
            ]);
        }

        $context = $this->buildContext($branch, $commits);

        return $this->render('export/commit_story.html.twig', $context);
    }

    #[Route('/branches', name: 'admin_commit_story_branches', methods: ['GET'])]
    public function branches(): Response
    {
        $branchList = $this->gitLogReader->listBranches();

        return $this->json($branchList);
    }

    private function renderBranchSelector(): Response
    {
        $branchList = $this->gitLogReader->listBranches();

        return $this->render('admin/commit_story/index.html.twig', [
            'branches' => $branchList,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $commits
     *
     * @return array<string, mixed>
     */
    private function buildContext(string $branch, array $commits): array
    {
        $totalInsertions = 0;
        $totalDeletions = 0;
        $allFiles = [];
        $authors = [];
        $typeBreakdown = [];

        foreach ($commits as $commit) {
            $totalInsertions += $commit['insertions'];
            $totalDeletions += $commit['deletions'];
            $allFiles = array_merge($allFiles, $commit['files']);

            if (!empty($commit['author'])) {
                $authors[$commit['author']] = true;
            }

            $type = $commit['type'];
            $typeBreakdown[$type] = ($typeBreakdown[$type] ?? 0) + 1;
        }

        $dateFrom = $commits[0]['date'] ?? null;
        $dateTo = end($commits)['date'] ?? null;

        return [
            'branch' => $branch,
            'commits' => $commits,
            'total_commits' => \count($commits),
            'total_insertions' => $totalInsertions,
            'total_deletions' => $totalDeletions,
            'total_files' => \count(array_unique($allFiles)),
            'authors' => array_keys($authors),
            'type_breakdown' => $typeBreakdown,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'generated_at' => new \DateTimeImmutable(),
        ];
    }
}
