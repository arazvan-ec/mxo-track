<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\GitLogReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

#[AsCommand(
    name: 'app:commit-story',
    description: 'Generate a static HTML commit story from a git branch',
)]
final class CommitStoryCommand extends Command
{
    public function __construct(
        private readonly GitLogReader $gitLogReader,
        private readonly Environment $twig,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('branch', InputArgument::REQUIRED, 'The git branch to generate the story for')
            ->addOption('base', null, InputOption::VALUE_REQUIRED, 'Base ref to compare against (default: auto-detect merge-base)')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output file path (default: docs/stories/YYYY-MM-DD-<slug>.html)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $branch = $input->getArgument('branch');
        $base = $input->getOption('base');

        try {
            $commits = $this->gitLogReader->getCommits($branch, $base);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        if ($commits === []) {
            $io->warning('No commits found between base and branch.');
        }

        $context = $this->buildTemplateContext($branch, $commits);
        $html = $this->twig->render('export/commit_story.html.twig', $context);

        $outputPath = $input->getOption('output') ?? $this->defaultOutputPath($branch);
        $outputDir = \dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0o755, true);
        }

        file_put_contents($outputPath, $html);

        $io->success(sprintf('Commit story generated: %s', $outputPath));
        $io->info(sprintf(
            'Branch: %s | Commits: %d | Files: %d | +%d -%d',
            $branch,
            $context['total_commits'],
            $context['total_files'],
            $context['total_insertions'],
            $context['total_deletions'],
        ));

        return self::SUCCESS;
    }

    /**
     * @param list<array<string, mixed>> $commits
     *
     * @return array<string, mixed>
     */
    private function buildTemplateContext(string $branch, array $commits): array
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

    private function defaultOutputPath(string $branch): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($branch));
        $slug = trim($slug, '-');

        return sprintf('%s/../docs/stories/%s-%s.html', $this->projectDir, date('Y-m-d'), $slug);
    }
}
