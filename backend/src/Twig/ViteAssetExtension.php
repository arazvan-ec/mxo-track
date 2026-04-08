<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Resolves Vite-built asset paths using the manifest.
 *
 * In production the manifest maps entry names to hashed filenames,
 * ensuring browsers always load the latest bundle after a deploy.
 * Falls back to stable (unhashed) paths when the manifest is absent
 * (e.g. during local development with `vite dev`).
 */
class ViteAssetExtension extends AbstractExtension
{
    private ?array $manifest = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('vite_entry_script', [$this, 'entryScript']),
            new TwigFunction('vite_entry_styles', [$this, 'entryStyles']),
        ];
    }

    /** Returns the <script> src for a Vite entry point. */
    public function entryScript(string $entry): string
    {
        $manifest = $this->loadManifest();
        $key = $entry . '.html';

        if (isset($manifest[$key]['file'])) {
            return '/app/' . $manifest[$key]['file'];
        }

        // Fallback: unhashed path (dev mode or manifest missing)
        return '/app/assets/' . $entry . '.js';
    }

    /** Returns an array of CSS hrefs associated with a Vite entry point (including transitive imports). */
    public function entryStyles(string $entry): array
    {
        $manifest = $this->loadManifest();
        $key = $entry . '.html';

        if (!isset($manifest[$key])) {
            return ['/app/assets/index.css'];
        }

        $css = [];
        $this->collectCss($manifest, $key, $css, []);

        return $css ?: ['/app/assets/index.css'];
    }

    /** Recursively collects CSS from an entry and its imported chunks. */
    private function collectCss(array $manifest, string $key, array &$css, array $visited): void
    {
        if (isset($visited[$key])) {
            return;
        }
        $visited[$key] = true;

        $entry = $manifest[$key] ?? null;
        if ($entry === null) {
            return;
        }

        // CSS directly on this chunk
        foreach ($entry['css'] ?? [] as $file) {
            $href = '/app/' . $file;
            if (!\in_array($href, $css, true)) {
                $css[] = $href;
            }
        }

        // CSS from imported chunks
        foreach ($entry['imports'] ?? [] as $import) {
            $this->collectCss($manifest, $import, $css, $visited);
        }
    }

    private function loadManifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        // Vite 5+ writes manifest to .vite/manifest.json inside outDir
        $path = $this->projectDir . '/public/app/.vite/manifest.json';

        if (!file_exists($path)) {
            $this->manifest = [];
            return $this->manifest;
        }

        $this->manifest = json_decode(file_get_contents($path), true) ?? [];
        return $this->manifest;
    }
}
