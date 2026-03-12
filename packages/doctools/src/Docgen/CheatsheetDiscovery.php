<?php declare(strict_types=1);

namespace Cognesy\Doctools\Docgen;

use Cognesy\Config\BasePath;
use Cognesy\Doctools\Docgen\Data\DiscoveredCheatsheet;
use Symfony\Component\Yaml\Yaml;

/**
 * Discovers CHEATSHEET.md files across packages and reads their frontmatter.
 */
class CheatsheetDiscovery
{
    public function __construct(
        private string $sourcePattern = 'packages/*/CHEATSHEET.md',
        private array $internal = [],
        private array $order = [],
    ) {}

    /**
     * Discover and return sorted cheatsheets, excluding internal packages.
     * @return DiscoveredCheatsheet[]
     */
    public function discover(): array
    {
        $files = glob(BasePath::get($this->sourcePattern)) ?: [];
        $cheatsheets = [];

        foreach ($files as $file) {
            $packageName = basename(dirname($file));

            if (in_array($packageName, $this->internal, true)) {
                continue;
            }

            $frontmatter = $this->parseFrontmatter($file);

            $cheatsheets[] = new DiscoveredCheatsheet(
                package: $frontmatter['package'] ?? $packageName,
                title: $frontmatter['title'] ?? ucfirst($packageName),
                description: $frontmatter['description'] ?? '',
                sourcePath: $file,
            );
        }

        return $this->sort($cheatsheets);
    }

    /**
     * Parse YAML frontmatter from a markdown file.
     */
    private function parseFrontmatter(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false || !str_starts_with($content, '---')) {
            return [];
        }

        $endPos = strpos($content, '---', 3);
        if ($endPos === false) {
            return [];
        }

        $yaml = substr($content, 3, $endPos - 3);

        try {
            return Yaml::parse($yaml) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Sort cheatsheets by configured package order.
     * @param DiscoveredCheatsheet[] $cheatsheets
     * @return DiscoveredCheatsheet[]
     */
    private function sort(array $cheatsheets): array
    {
        $order = $this->order;

        usort($cheatsheets, function (DiscoveredCheatsheet $a, DiscoveredCheatsheet $b) use ($order) {
            $aIndex = array_search($a->package, $order, true);
            $bIndex = array_search($b->package, $order, true);

            if ($aIndex !== false && $bIndex !== false) {
                return (int) $aIndex - (int) $bIndex;
            }
            if ($aIndex !== false) {
                return -1;
            }
            if ($bIndex !== false) {
                return 1;
            }
            return strcmp($a->package, $b->package);
        });

        return $cheatsheets;
    }
}
