<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen;

use Cognesy\Auxiliary\Mintlify\NavigationGroup;
use Cognesy\Auxiliary\Mintlify\NavigationItem;
use Cognesy\Config\BasePath;
use Cognesy\Doctor\Docgen\Data\DiscoveredPackage;
use Cognesy\Doctor\Docgen\Data\GenerationResult;
use Cognesy\Doctor\Markdown\MarkdownFile;
use Cognesy\Utils\Files;

class PackageDocsBuilder
{
    private string $targetBaseDir;
    private string $format;

    public function __construct(string $targetBaseDir, string $format = 'mintlify')
    {
        $this->targetBaseDir = BasePath::get($targetBaseDir);
        $this->format = $format;
    }

    /**
     * Process a package's docs
     */
    public function processPackage(DiscoveredPackage $package): GenerationResult
    {
        $startTime = microtime(true);
        $filesProcessed = 0;

        try {
            $targetPath = $this->targetBaseDir . '/' . $package->targetDir;

            Files::removeDirectory($targetPath);
            Files::copyDirectory($package->docsPath, $targetPath);

            // Convert file extensions based on format
            if ($this->format === 'mintlify') {
                Files::renameFileExtensions($targetPath, 'md', 'mdx');
            }

            $this->inlineExternalCodeblocks($targetPath);
            $filesProcessed = $this->countFiles($targetPath);

            return GenerationResult::success(
                filesProcessed: $filesProcessed,
                duration: microtime(true) - $startTime,
            );
        } catch (\Throwable $e) {
            return GenerationResult::failure(
                errors: [$e->getMessage()],
                duration: microtime(true) - $startTime,
            );
        }
    }

    /**
     * Build Mintlify navigation group for a package
     */
    public function buildMintlifyNavigation(DiscoveredPackage $package): NavigationGroup
    {
        $targetDir = $package->targetDir;
        $extension = $this->format === 'mintlify' ? 'mdx' : 'md';
        $targetPath = $this->targetBaseDir . '/' . $targetDir;

        $group = new NavigationGroup($package->getTitle());
        $pages = $this->scanDirectoryForPages($targetPath, $targetDir, $extension);

        foreach ($pages as $page) {
            $group->pages[] = NavigationItem::fromString($page);
        }

        return $group;
    }

    /**
     * Build MkDocs navigation array for a package
     */
    public function buildMkDocsNavigation(DiscoveredPackage $package): array
    {
        $targetDir = $package->targetDir;
        $targetPath = $this->targetBaseDir . '/' . $targetDir;

        return $this->buildDirectoryNav($targetPath, $targetDir);
    }

    /**
     * Scan directory for pages, returning relative paths
     */
    private function scanDirectoryForPages(string $dirPath, string $relativePath, string $extension): array
    {
        $pages = [];
        $entries = scandir($dirPath);

        if ($entries === false) {
            return $pages;
        }

        // Sort entries - index first, then alphabetically
        $entries = array_filter($entries, fn($e) => $e !== '.' && $e !== '..');
        usort($entries, function($a, $b) {
            if (str_starts_with($a, 'index.')) return -1;
            if (str_starts_with($b, 'index.')) return 1;
            return strcmp($a, $b);
        });

        foreach ($entries as $entry) {
            $entryPath = $dirPath . '/' . $entry;

            if (is_file($entryPath) && str_ends_with($entry, '.' . $extension)) {
                // Remove extension for Mintlify paths
                $pagePath = $relativePath . '/' . basename($entry, '.' . $extension);
                $pages[] = $pagePath;
            } elseif (is_dir($entryPath)) {
                // Recursively scan subdirectories
                $subPages = $this->scanDirectoryForPages(
                    $entryPath,
                    $relativePath . '/' . $entry,
                    $extension
                );
                $pages = array_merge($pages, $subPages);
            }
        }

        return $pages;
    }

    /**
     * Build directory navigation for MkDocs
     */
    private function buildDirectoryNav(string $dirPath, string $relativeBasePath): array
    {
        $nav = [];
        $entries = scandir($dirPath);

        if ($entries === false) {
            return $nav;
        }

        // Sort entries - index first, then alphabetically
        $entries = array_filter($entries, fn($e) => $e !== '.' && $e !== '..');
        $entries = array_values($entries);
        usort($entries, function($a, $b) use ($dirPath) {
            $aIsDir = is_dir($dirPath . '/' . $a);
            $bIsDir = is_dir($dirPath . '/' . $b);
            // Files before directories
            if ($aIsDir !== $bIsDir) {
                return $aIsDir ? 1 : -1;
            }
            // Index files first
            if (str_starts_with($a, 'index.')) return -1;
            if (str_starts_with($b, 'index.')) return 1;
            return strcmp($a, $b);
        });

        foreach ($entries as $entry) {
            $entryPath = $dirPath . '/' . $entry;

            if (is_file($entryPath) && str_ends_with($entry, '.md')) {
                $title = $this->formatTitle(basename($entry, '.md'));
                $path = $relativeBasePath . '/' . $entry;
                $nav[] = [$title => $path];
            } elseif (is_dir($entryPath)) {
                $subNav = $this->buildDirectoryNav($entryPath, $relativeBasePath . '/' . $entry);
                if (!empty($subNav)) {
                    $dirTitle = $this->formatTitle($entry);
                    $nav[] = [$dirTitle => $subNav];
                }
            }
        }

        return $nav;
    }

    /**
     * Format title from file/directory name
     */
    private function formatTitle(string $name): string
    {
        if ($name === 'index') {
            return 'Overview';
        }
        return ucwords(str_replace(['-', '_'], ' ', $name));
    }

    /**
     * Inline external codeblocks in markdown files
     */
    private function inlineExternalCodeblocks(string $targetPath): void
    {
        $extension = $this->format === 'mintlify' ? 'mdx' : 'md';
        $docFiles = array_merge(
            glob("$targetPath/*.$extension") ?: [],
            glob("$targetPath/**/*.$extension") ?: [],
        );

        foreach ($docFiles as $docFile) {
            $realPath = realpath($docFile);
            if ($realPath === false) {
                continue;
            }

            $content = file_get_contents($realPath);
            if ($content === false) {
                continue;
            }

            $markdown = MarkdownFile::fromString($content, $realPath);
            if (!$markdown->hasCodeblocks()) {
                continue;
            }

            try {
                $newMarkdown = $markdown->withInlinedCodeBlocks();
                file_put_contents($docFile, $newMarkdown->toString());
            } catch (\Throwable $e) {
                // Continue processing other files
            }
        }
    }

    /**
     * Count files in directory
     */
    private function countFiles(string $dirPath): int
    {
        $count = 0;
        $extension = $this->format === 'mintlify' ? 'mdx' : 'md';
        $files = glob("$dirPath/*.$extension") ?: [];
        $count += count($files);

        $subdirs = glob("$dirPath/*", GLOB_ONLYDIR) ?: [];
        foreach ($subdirs as $subdir) {
            $count += $this->countFiles($subdir);
        }

        return $count;
    }
}
