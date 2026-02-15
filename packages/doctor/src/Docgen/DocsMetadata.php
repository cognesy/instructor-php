<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen;

use Symfony\Component\Yaml\Yaml;

/**
 * Reads and provides ordering metadata for documentation files
 *
 * Supports:
 * 1. _meta.yaml in directories - explicit order list
 * 2. Front matter sidebarPosition - per-file ordering
 * 3. Hardcoded defaults - common documentation patterns
 */
class DocsMetadata
{
    private array $metaCache = [];
    private array $frontMatterCache = [];

    /**
     * Get ordered list of items for a directory
     * Returns null if no ordering metadata found
     */
    public function getDirectoryOrder(string $dirPath): ?array
    {
        if (isset($this->metaCache[$dirPath])) {
            return $this->metaCache[$dirPath];
        }

        $metaFile = $dirPath . '/_meta.yaml';
        if (!file_exists($metaFile)) {
            $metaFile = $dirPath . '/_meta.yml';
        }

        if (!file_exists($metaFile)) {
            $this->metaCache[$dirPath] = null;
            return null;
        }

        try {
            $content = file_get_contents($metaFile);
            if ($content === false) {
                $this->metaCache[$dirPath] = null;
                return null;
            }
            $meta = Yaml::parse($content);

            // Support both simple list and object with 'order' key
            if (isset($meta['order']) && is_array($meta['order'])) {
                $order = $meta['order'];
            } elseif (is_array($meta) && !isset($meta['order'])) {
                $order = $meta;
            } else {
                $order = null;
            }

            $this->metaCache[$dirPath] = $order;
            return $order;
        } catch (\Throwable $e) {
            $this->metaCache[$dirPath] = null;
            return null;
        }
    }

    /**
     * Get sidebar position from file's front matter
     * Returns null if not specified
     */
    public function getSidebarPosition(string $filePath): ?int
    {
        if (isset($this->frontMatterCache[$filePath])) {
            return $this->frontMatterCache[$filePath];
        }

        if (!file_exists($filePath)) {
            $this->frontMatterCache[$filePath] = null;
            return null;
        }

        try {
            $content = file_get_contents($filePath);
            if ($content === false) {
                $this->frontMatterCache[$filePath] = null;
                return null;
            }
            $frontMatter = $this->parseFrontMatter($content);

            $position = $frontMatter['sidebarPosition'] ?? $frontMatter['sidebar_position'] ?? null;
            $this->frontMatterCache[$filePath] = $position !== null ? (int) $position : null;

            return $this->frontMatterCache[$filePath];
        } catch (\Throwable $e) {
            $this->frontMatterCache[$filePath] = null;
            return null;
        }
    }

    /**
     * Parse YAML front matter from markdown content
     */
    private function parseFrontMatter(string $content): array
    {
        if (!str_starts_with($content, '---')) {
            return [];
        }

        $endPos = strpos($content, '---', 3);
        if ($endPos === false) {
            return [];
        }

        $yamlContent = substr($content, 3, $endPos - 3);

        try {
            return Yaml::parse($yamlContent) ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Sort items using metadata, with fallback to defaults
     *
     * @param string $dirPath Directory containing items
     * @param array $files List of filenames (with extension)
     * @param array $dirs List of directory names
     * @return array{files: array, dirs: array} Sorted arrays
     */
    public function sortItems(string $dirPath, array $files, array $dirs): array
    {
        $order = $this->getDirectoryOrder($dirPath);

        if ($order !== null) {
            // Use explicit order from _meta.yaml
            $files = $this->sortByExplicitOrder($files, $order, fn($f) => pathinfo($f, PATHINFO_FILENAME));
            $dirs = $this->sortByExplicitOrder($dirs, $order, fn($d) => $d);
        } else {
            // Fall back to front matter + defaults
            $files = $this->sortFilesByMetadata($dirPath, $files);
            $dirs = $this->sortDirectoriesByDefaults($dirs);
        }

        return ['files' => $files, 'dirs' => $dirs];
    }

    /**
     * Sort items by explicit order list
     * @param callable(mixed): string $getKey
     */
    private function sortByExplicitOrder(array $items, array $order, callable $getKey): array
    {
        usort($items, function($a, $b) use ($order, $getKey) {
            $aKey = $getKey($a);
            $bKey = $getKey($b);

            $aIndex = array_search($aKey, $order, true);
            $bIndex = array_search($bKey, $order, true);

            // Both in order list - sort by position
            if ($aIndex !== false && $bIndex !== false) {
                return $aIndex <=> $bIndex;
            }
            // Only a in list - a comes first
            if ($aIndex !== false) {
                return -1;
            }
            // Only b in list - b comes first
            if ($bIndex !== false) {
                return 1;
            }
            // Neither in list - alphabetical
            return strcmp($aKey, $bKey);
        });

        return $items;
    }

    /**
     * Sort files using front matter sidebarPosition, then defaults
     */
    private function sortFilesByMetadata(string $dirPath, array $files): array
    {
        usort($files, function($a, $b) use ($dirPath) {
            $aPath = $dirPath . '/' . $a;
            $bPath = $dirPath . '/' . $b;

            $aPos = $this->getSidebarPosition($aPath);
            $bPos = $this->getSidebarPosition($bPath);

            // Both have position - sort by position
            if ($aPos !== null && $bPos !== null) {
                return $aPos - $bPos;
            }
            // Only a has position - a comes first
            if ($aPos !== null) {
                return -1;
            }
            // Only b has position - b comes first
            if ($bPos !== null) {
                return 1;
            }

            // Fall back to default ordering
            return $this->compareByDefaultFileOrder($a, $b);
        });

        return $files;
    }

    /**
     * Sort directories using default ordering
     */
    private function sortDirectoriesByDefaults(array $dirs): array
    {
        usort($dirs, fn($a, $b) => $this->compareByDefaultDirOrder($a, $b));
        return $dirs;
    }

    /**
     * Default file ordering for common documentation patterns
     */
    private function compareByDefaultFileOrder(string $a, string $b): int
    {
        $aOrder = $this->getDefaultFileOrder($a);
        $bOrder = $this->getDefaultFileOrder($b);

        if ($aOrder !== $bOrder) {
            return $aOrder - $bOrder;
        }
        return strcmp($a, $b);
    }

    private function getDefaultFileOrder(string $filename): int
    {
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        $priority = [
            'index' => 0,
            'introduction' => 1,
            'overview' => 2,
            'quickstart' => 3,
            'getting-started' => 4,
            'setup' => 5,
            'installation' => 6,
            'configuration' => 7,
            'usage' => 8,
            'upgrade' => 100,
            'cli_tools' => 101,
            'cli-tools' => 101,
            'contributing' => 200,
            'changelog' => 201,
        ];

        return $priority[$basename] ?? 50;
    }

    /**
     * Default directory ordering for common documentation patterns
     */
    private function compareByDefaultDirOrder(string $a, string $b): int
    {
        $aOrder = $this->getDefaultDirOrder($a);
        $bOrder = $this->getDefaultDirOrder($b);

        if ($aOrder !== $bOrder) {
            return $aOrder - $bOrder;
        }
        return strcmp($a, $b);
    }

    private function getDefaultDirOrder(string $dirname): int
    {
        $priority = [
            'concepts' => 1,
            'essentials' => 2,
            'basics' => 3,
            'getting-started' => 4,
            'modes' => 10,
            'streaming' => 11,
            'embeddings' => 12,
            'advanced' => 20,
            'techniques' => 21,
            'internals' => 30,
            'troubleshooting' => 40,
            'misc' => 100,
            'reference' => 101,
        ];

        return $priority[$dirname] ?? 50;
    }
}
