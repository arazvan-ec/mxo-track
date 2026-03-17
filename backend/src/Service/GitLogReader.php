<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

final class GitLogReader
{
    private const COMMIT_SEPARATOR = '---COMMIT_SEP---';
    private const BODY_END = '---BODY_END---';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function isAvailable(): bool
    {
        return is_dir($this->projectDir . '/.git');
    }

    /**
     * @return list<array{
     *     hash: string,
     *     full_hash: string,
     *     date: \DateTimeImmutable,
     *     author: string,
     *     subject: string,
     *     body: string,
     *     type: string,
     *     files: list<string>,
     *     insertions: int,
     *     deletions: int,
     *     files_count: int,
     * }>
     */
    public function getCommits(string $branch, ?string $base = null): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        if ($base === null) {
            $base = $this->detectMergeBase($branch);
        }

        $range = $base . '..' . $branch;

        // Single git log call with --stat to avoid N+1 process calls
        $format = self::COMMIT_SEPARATOR . '%n%H%n%h%n%aI%n%aN%n%s%n%b' . self::BODY_END;
        $process = $this->run(['git', 'log', '--reverse', '--stat', '--stat-width=200', '--format=' . $format, $range]);

        $output = trim($process->getOutput());
        if ($output === '') {
            return [];
        }

        return $this->parseLogOutput($output);
    }

    public function detectType(string $subject): string
    {
        if (preg_match('/^(feat|fix|test|docs|refactor|chore)[\(:]/', $subject, $matches)) {
            return $matches[1];
        }

        return 'other';
    }

    /**
     * @return array{files: list<string>, insertions: int, deletions: int, files_count: int}
     */
    public function parseDiffStat(string $statOutput): array
    {
        if (trim($statOutput) === '') {
            return ['files' => [], 'insertions' => 0, 'deletions' => 0, 'files_count' => 0];
        }

        $lines = explode("\n", trim($statOutput));
        $files = [];
        $insertions = 0;
        $deletions = 0;

        foreach ($lines as $line) {
            $line = trim($line);

            // Summary line: "3 files changed, 66 insertions(+), 2 deletions(-)"
            if (preg_match('/^\d+ files? changed/', $line)) {
                if (preg_match('/(\d+) insertions?\(\+\)/', $line, $m)) {
                    $insertions = (int) $m[1];
                }
                if (preg_match('/(\d+) deletions?\(-\)/', $line, $m)) {
                    $deletions = (int) $m[1];
                }
                continue;
            }

            // File line: " src/Service/Foo.php | 25 +++---"
            if (preg_match('/^\s*(.+?)\s+\|/', $line, $m)) {
                $files[] = trim($m[1]);
            }
        }

        return [
            'files' => $files,
            'insertions' => $insertions,
            'deletions' => $deletions,
            'files_count' => \count($files),
        ];
    }

    /**
     * @return list<string>
     */
    public function listBranches(): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $process = $this->run(['git', 'branch', '-a', '--format=%(refname:short)']);
        $output = trim($process->getOutput());
        if ($output === '') {
            return [];
        }

        $branches = array_map('trim', explode("\n", $output));

        // Deduplicate and sort: local branches first, then remote
        $branches = array_values(array_unique($branches));
        sort($branches);

        return $branches;
    }

    private function detectMergeBase(string $branch): string
    {
        // Try main first, fallback to master
        foreach (['main', 'master'] as $baseBranch) {
            try {
                $process = $this->run(['git', 'merge-base', $baseBranch, $branch]);

                return trim($process->getOutput());
            } catch (\RuntimeException) {
                continue;
            }
        }

        throw new \RuntimeException('Could not detect merge base: neither "main" nor "master" branch found.');
    }

    /**
     * @return list<array{hash: string, full_hash: string, date: \DateTimeImmutable, author: string, subject: string, body: string, type: string, files: list<string>, insertions: int, deletions: int, files_count: int}>
     */
    private function parseLogOutput(string $output): array
    {
        $commits = [];
        $blocks = explode(self::COMMIT_SEPARATOR, $output);

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            // Split at BODY_END to separate commit metadata from stat
            $parts = explode(self::BODY_END, $block, 2);
            $commitPart = trim($parts[0]);
            $statPart = trim($parts[1] ?? '');

            $lines = explode("\n", $commitPart);
            if (\count($lines) < 5) {
                continue;
            }

            $fullHash = $lines[0];
            $hash = $lines[1];
            $date = new \DateTimeImmutable($lines[2]);
            $author = $lines[3];
            $subject = $lines[4];
            $body = trim(implode("\n", \array_slice($lines, 5)));

            $stat = $this->parseDiffStat($statPart);

            $commits[] = [
                'hash' => $hash,
                'full_hash' => $fullHash,
                'date' => $date,
                'author' => $author,
                'subject' => $subject,
                'body' => $body,
                'type' => $this->detectType($subject),
                'files' => $stat['files'],
                'insertions' => $stat['insertions'],
                'deletions' => $stat['deletions'],
                'files_count' => $stat['files_count'],
            ];
        }

        return $commits;
    }

    private function run(array $command): Process
    {
        $process = new Process($command, $this->projectDir);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                'Git command failed: %s (exit code %d)',
                trim($process->getErrorOutput()) ?: trim($process->getOutput()),
                $process->getExitCode(),
            ));
        }

        return $process;
    }
}
