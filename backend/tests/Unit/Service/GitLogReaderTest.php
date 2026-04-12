<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\GitLogReader;
use PHPUnit\Framework\TestCase;

final class GitLogReaderTest extends TestCase
{
    private GitLogReader $reader;

    protected function setUp(): void
    {
        // Use the monorepo root (where .git lives), one level above backend/
        $this->reader = new GitLogReader(dirname(__DIR__, 4));
    }

    /**
     * @dataProvider conventionalPrefixProvider
     */
    public function testDetectTypeFromConventionalPrefix(string $subject, string $expectedType): void
    {
        self::assertSame($expectedType, $this->reader->detectType($subject));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function conventionalPrefixProvider(): iterable
    {
        yield 'feat with colon' => ['feat: add new feature', 'feat'];
        yield 'fix with colon' => ['fix: correct calculation', 'fix'];
        yield 'test with colon' => ['test: add unit tests', 'test'];
        yield 'docs with colon' => ['docs: update readme', 'docs'];
        yield 'refactor with colon' => ['refactor: extract method', 'refactor'];
        yield 'chore with colon' => ['chore: update dependencies', 'chore'];
        yield 'feat with scope' => ['feat(route): add optimization', 'feat'];
        yield 'fix with scope' => ['fix(delivery): null check', 'fix'];
    }

    public function testDetectTypeReturnsOtherForUnknownPrefix(): void
    {
        self::assertSame('other', $this->reader->detectType('Update something'));
        self::assertSame('other', $this->reader->detectType('Merge branch main'));
        self::assertSame('other', $this->reader->detectType('Initial commit'));
        self::assertSame('other', $this->reader->detectType(''));
    }

    public function testParseDiffStatExtractsFilesAndCounts(): void
    {
        $statOutput = <<<'STAT'
 src/Service/Foo.php      | 25 +++++++++++++++++++++++-
 src/Command/Bar.php      |  3 ++-
 tests/Unit/FooTest.php   | 40 ++++++++++++++++++++++++++++++++++++++++
 3 files changed, 66 insertions(+), 2 deletions(-)
STAT;

        $result = $this->reader->parseDiffStat($statOutput);

        self::assertSame(['src/Service/Foo.php', 'src/Command/Bar.php', 'tests/Unit/FooTest.php'], $result['files']);
        self::assertSame(66, $result['insertions']);
        self::assertSame(2, $result['deletions']);
        self::assertSame(3, $result['files_count']);
    }

    public function testParseDiffStatHandlesEmptyOutput(): void
    {
        $result = $this->reader->parseDiffStat('');

        self::assertSame([], $result['files']);
        self::assertSame(0, $result['insertions']);
        self::assertSame(0, $result['deletions']);
        self::assertSame(0, $result['files_count']);
    }

    public function testParseDiffStatHandlesInsertionsOnly(): void
    {
        $statOutput = <<<'STAT'
 src/NewFile.php | 10 ++++++++++
 1 file changed, 10 insertions(+)
STAT;

        $result = $this->reader->parseDiffStat($statOutput);

        self::assertSame(['src/NewFile.php'], $result['files']);
        self::assertSame(10, $result['insertions']);
        self::assertSame(0, $result['deletions']);
    }

    public function testParseDiffStatHandlesDeletionsOnly(): void
    {
        $statOutput = <<<'STAT'
 src/OldFile.php | 5 -----
 1 file changed, 5 deletions(-)
STAT;

        $result = $this->reader->parseDiffStat($statOutput);

        self::assertSame(['src/OldFile.php'], $result['files']);
        self::assertSame(0, $result['insertions']);
        self::assertSame(5, $result['deletions']);
    }

    public function testGetCommitsReturnsStructuredArray(): void
    {
        // Test against the actual repo - get last 3 commits from current branch
        $commits = $this->reader->getCommits('HEAD', 'HEAD~3');

        self::assertCount(3, $commits);

        foreach ($commits as $commit) {
            self::assertArrayHasKey('hash', $commit);
            self::assertArrayHasKey('full_hash', $commit);
            self::assertArrayHasKey('date', $commit);
            self::assertArrayHasKey('author', $commit);
            self::assertArrayHasKey('subject', $commit);
            self::assertArrayHasKey('body', $commit);
            self::assertArrayHasKey('type', $commit);
            self::assertArrayHasKey('files', $commit);
            self::assertArrayHasKey('insertions', $commit);
            self::assertArrayHasKey('deletions', $commit);
            self::assertArrayHasKey('files_count', $commit);

            self::assertInstanceOf(\DateTimeImmutable::class, $commit['date']);
            self::assertIsString($commit['hash']);
            self::assertNotEmpty($commit['hash']);
            self::assertIsArray($commit['files']);
            self::assertContains($commit['type'], ['feat', 'fix', 'test', 'docs', 'refactor', 'chore', 'other']);
        }
    }

    public function testGetCommitsThrowsOnInvalidBranch(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->reader->getCommits('nonexistent-branch-xyz-123');
    }

    public function testGetCommitsReturnsEmptyForIdenticalRefs(): void
    {
        $commits = $this->reader->getCommits('HEAD', 'HEAD');

        self::assertSame([], $commits);
    }
}
