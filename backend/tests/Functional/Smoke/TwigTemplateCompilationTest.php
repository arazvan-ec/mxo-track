<?php

declare(strict_types=1);

namespace App\Tests\Functional\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;
use Twig\Environment;
use Twig\Error\Error as TwigError;

/**
 * Verifies that all Twig templates compile without syntax errors.
 * This catches typos, missing blocks, invalid filters, etc. at test time
 * rather than in production.
 */
final class TwigTemplateCompilationTest extends KernelTestCase
{
    public function testAllTemplatesCompileWithoutErrors(): void
    {
        self::bootKernel();

        $twig = self::getContainer()->get(Environment::class);
        $templateDir = self::getContainer()->getParameter('kernel.project_dir') . '/templates';

        $finder = (new Finder())->files()->name('*.twig')->in($templateDir);

        $errors = [];

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();

            try {
                $twig->load($relativePath);
            } catch (TwigError $e) {
                $errors[] = sprintf(
                    "Template '%s': %s",
                    $relativePath,
                    $e->getMessage(),
                );
            }
        }

        self::assertSame(
            [],
            $errors,
            "Found Twig compilation errors:\n" . implode("\n", $errors),
        );
    }

    public function testTemplateDirectoryContainsTemplates(): void
    {
        self::bootKernel();

        $templateDir = self::getContainer()->getParameter('kernel.project_dir') . '/templates';
        $finder = (new Finder())->files()->name('*.twig')->in($templateDir);

        self::assertGreaterThan(
            5,
            $finder->count(),
            'Expected to find at least 5 Twig templates — check template directory.',
        );
    }
}
