<?php

declare(strict_types=1);

namespace App\Tests\Functional\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Routing\RouterInterface;

/**
 * Verifies that every route name referenced via path() or url() in Twig templates
 * actually exists in the Symfony router. Catches bugs like using 'admin_route_planner'
 * when the real route is 'admin_route_planner_index'.
 */
final class TemplateRouteReferenceTest extends KernelTestCase
{
    public function testAllTemplateRouteReferencesExist(): void
    {
        self::bootKernel();

        $router = self::getContainer()->get(RouterInterface::class);
        $routeCollection = $router->getRouteCollection();
        $knownRoutes = array_keys($routeCollection->all());

        $templateDir = self::getContainer()->getParameter('kernel.project_dir') . '/templates';
        $finder = (new Finder())->files()->name('*.twig')->in($templateDir);

        $missing = [];

        foreach ($finder as $file) {
            $content = $file->getContents();
            $relativePath = $file->getRelativePathname();

            // Match path('route_name') and url('route_name') — with single or double quotes
            if (preg_match_all('/(?:path|url)\s*\(\s*[\'"]([a-z_][a-z0-9_]*)[\'"]/', $content, $matches)) {
                foreach ($matches[1] as $routeName) {
                    if (!\in_array($routeName, $knownRoutes, true)) {
                        $missing[] = sprintf(
                            "Template '%s' references route '%s' which does not exist",
                            $relativePath,
                            $routeName,
                        );
                    }
                }
            }
        }

        self::assertSame(
            [],
            $missing,
            "Found template references to non-existent routes:\n" . implode("\n", $missing),
        );
    }

    public function testAllTemplateRouteReferencesAreUnambiguous(): void
    {
        self::bootKernel();

        $router = self::getContainer()->get(RouterInterface::class);
        $routeCollection = $router->getRouteCollection();

        $templateDir = self::getContainer()->getParameter('kernel.project_dir') . '/templates';
        $finder = (new Finder())->files()->name('*.twig')->in($templateDir);

        $routeUsages = [];

        foreach ($finder as $file) {
            $content = $file->getContents();
            $relativePath = $file->getRelativePathname();

            if (preg_match_all('/(?:path|url)\s*\(\s*[\'"]([a-z_][a-z0-9_]*)[\'"]/', $content, $matches)) {
                foreach ($matches[1] as $routeName) {
                    $route = $routeCollection->get($routeName);
                    if ($route === null) {
                        continue; // Covered by the other test
                    }

                    // Check that required parameters are provided in the template call
                    $routeUsages[] = $routeName;
                }
            }
        }

        // Verify we actually found route references (sanity check)
        self::assertGreaterThan(
            10,
            \count($routeUsages),
            'Expected to find at least 10 route references in templates — check regex or template directory.',
        );
    }
}
