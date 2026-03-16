<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\CommitStoryCommand;
use App\Service\GitLogReader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Twig\Environment;

final class CommitStoryCommandTest extends TestCase
{
    private GitLogReader&MockObject $gitLogReader;
    private Environment&MockObject $twig;
    private string $projectDir;

    protected function setUp(): void
    {
        $this->gitLogReader = $this->createMock(GitLogReader::class);
        $this->twig = $this->createMock(Environment::class);
        $this->projectDir = sys_get_temp_dir();
    }

    public function testExecuteGeneratesHtmlFile(): void
    {
        $commits = $this->sampleCommits();

        $this->gitLogReader->expects(self::once())
            ->method('getCommits')
            ->with('feature/my-branch', null)
            ->willReturn($commits);

        $this->twig->expects(self::once())
            ->method('render')
            ->willReturn('<html>Story</html>');

        $outputPath = $this->projectDir . '/test-story-' . uniqid() . '.html';

        $command = new CommitStoryCommand($this->gitLogReader, $this->twig, $this->projectDir);
        $tester = new CommandTester($command);
        $tester->execute([
            'branch' => 'feature/my-branch',
            '--output' => $outputPath,
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertFileExists($outputPath);
        self::assertSame('<html>Story</html>', file_get_contents($outputPath));
        self::assertStringContainsString('feature/my-branch', $tester->getDisplay());

        @unlink($outputPath);
    }

    public function testExecuteWithBranchNotFoundReturnsFailure(): void
    {
        $this->gitLogReader->expects(self::once())
            ->method('getCommits')
            ->willThrowException(new \RuntimeException('Git command failed: unknown revision'));

        $command = new CommitStoryCommand($this->gitLogReader, $this->twig, $this->projectDir);
        $tester = new CommandTester($command);
        $tester->execute(['branch' => 'nonexistent-branch']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('unknown revision', $tester->getDisplay());
    }

    public function testExecuteWithNoCommitsShowsWarning(): void
    {
        $this->gitLogReader->expects(self::once())
            ->method('getCommits')
            ->willReturn([]);

        $this->twig->expects(self::once())
            ->method('render')
            ->willReturn('<html>No commits</html>');

        $outputPath = $this->projectDir . '/test-empty-' . uniqid() . '.html';

        $command = new CommitStoryCommand($this->gitLogReader, $this->twig, $this->projectDir);
        $tester = new CommandTester($command);
        $tester->execute([
            'branch' => 'empty-branch',
            '--output' => $outputPath,
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('No commits', $tester->getDisplay());

        @unlink($outputPath);
    }

    public function testBaseOptionIsPassedToGitLogReader(): void
    {
        $this->gitLogReader->expects(self::once())
            ->method('getCommits')
            ->with('my-branch', 'abc123')
            ->willReturn($this->sampleCommits());

        $this->twig->method('render')->willReturn('<html></html>');

        $outputPath = $this->projectDir . '/test-base-' . uniqid() . '.html';

        $command = new CommitStoryCommand($this->gitLogReader, $this->twig, $this->projectDir);
        $tester = new CommandTester($command);
        $tester->execute([
            'branch' => 'my-branch',
            '--base' => 'abc123',
            '--output' => $outputPath,
        ]);

        self::assertSame(0, $tester->getStatusCode());

        @unlink($outputPath);
    }

    public function testTwigReceivesExpectedVariables(): void
    {
        $commits = $this->sampleCommits();

        $this->gitLogReader->method('getCommits')->willReturn($commits);

        $this->twig->expects(self::once())
            ->method('render')
            ->willReturnCallback(function (string $template, array $context): string {
                self::assertSame('export/commit_story.html.twig', $template);
                self::assertArrayHasKey('branch', $context);
                self::assertArrayHasKey('commits', $context);
                self::assertArrayHasKey('total_commits', $context);
                self::assertArrayHasKey('total_insertions', $context);
                self::assertArrayHasKey('total_deletions', $context);
                self::assertArrayHasKey('total_files', $context);
                self::assertArrayHasKey('authors', $context);
                self::assertArrayHasKey('type_breakdown', $context);
                self::assertArrayHasKey('date_from', $context);
                self::assertArrayHasKey('date_to', $context);
                self::assertArrayHasKey('generated_at', $context);

                self::assertSame(3, $context['total_commits']);
                self::assertSame(76, $context['total_insertions']);
                self::assertSame(7, $context['total_deletions']);
                self::assertSame(['Dev User'], $context['authors']);
                self::assertSame(['feat' => 1, 'test' => 1, 'fix' => 1], $context['type_breakdown']);

                return '<html>rendered</html>';
            });

        $outputPath = $this->projectDir . '/test-vars-' . uniqid() . '.html';

        $command = new CommitStoryCommand($this->gitLogReader, $this->twig, $this->projectDir);
        $tester = new CommandTester($command);
        $tester->execute([
            'branch' => 'test-branch',
            '--output' => $outputPath,
        ]);

        @unlink($outputPath);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sampleCommits(): array
    {
        return [
            [
                'hash' => 'abc1234',
                'full_hash' => 'abc1234567890abcdef',
                'date' => new \DateTimeImmutable('2026-03-16 10:00:00'),
                'author' => 'Dev User',
                'subject' => 'feat: add new feature',
                'body' => 'Detailed description',
                'type' => 'feat',
                'files' => ['src/Foo.php', 'src/Bar.php'],
                'insertions' => 50,
                'deletions' => 5,
                'files_count' => 2,
            ],
            [
                'hash' => 'def5678',
                'full_hash' => 'def5678901234567890',
                'date' => new \DateTimeImmutable('2026-03-16 10:30:00'),
                'author' => 'Dev User',
                'subject' => 'test: add tests for feature',
                'body' => '',
                'type' => 'test',
                'files' => ['tests/FooTest.php'],
                'insertions' => 20,
                'deletions' => 0,
                'files_count' => 1,
            ],
            [
                'hash' => 'ghi9012',
                'full_hash' => 'ghi9012345678901234',
                'date' => new \DateTimeImmutable('2026-03-16 11:00:00'),
                'author' => 'Dev User',
                'subject' => 'fix: correct edge case',
                'body' => '',
                'type' => 'fix',
                'files' => ['src/Foo.php'],
                'insertions' => 6,
                'deletions' => 2,
                'files_count' => 1,
            ],
        ];
    }
}
