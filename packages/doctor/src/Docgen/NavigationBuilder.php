<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen;

use Cognesy\Doctor\Docgen\Data\DiscoveredPackage;
use Cognesy\Doctor\Docgen\Data\DocsConfig;
use Cognesy\InstructorHub\Data\ExampleGroup;

/**
 * Builds navigation structures for MkDocs and Mintlify from autodiscovered content.
 *
 * Navigation structure:
 * - Main (landing page + changelog)
 * - Packages (listing page + each package as subsection)
 * - Cookbook (examples from ExampleRepository)
 */
class NavigationBuilder
{
    private DocsMetadata $metadata;

    public function __construct(
        private DocsConfig $config,
        private string $targetDir,
        string $format = 'mkdocs', // 'mkdocs' or 'mintlify' (reserved for future use)
    ) {
        $this->metadata = new DocsMetadata();
    }

    /**
     * Build complete MkDocs navigation array
     * @param DiscoveredPackage[] $packages
     * @param ExampleGroup[] $exampleGroups
     * @param array $releaseNotes
     */
    public function buildMkDocsNav(array $packages, array $exampleGroups, array $releaseNotes): array
    {
        $nav = [];

        // Main section - landing page + changelog
        $mainNav = $this->buildMainNav($releaseNotes);
        $nav[] = ['Main' => $mainNav];

        // Packages section - listing page + each package as subsection
        $packagesNav = $this->buildPackagesNav($packages);
        if (!empty($packagesNav)) {
            $nav[] = ['Packages' => $packagesNav];
        }

        // Cookbook section - examples
        $cookbookNav = $this->buildCookbookNav($exampleGroups);
        if (!empty($cookbookNav)) {
            $nav[] = ['Cookbook' => $cookbookNav];
        }

        return $nav;
    }

    /**
     * Build Main section navigation (autodiscovered from docs root + changelog)
     */
    private function buildMainNav(array $releaseNotes): array
    {
        $nav = [];

        // Scan for markdown files in docs root (excluding special directories)
        $allFiles = glob($this->targetDir . '/*.md') ?: [];
        $files = array_map('basename', $allFiles);

        // Sort using metadata with fallback to defaults
        $sorted = $this->metadata->sortItems($this->targetDir, $files, []);
        $files = $sorted['files'];

        foreach ($files as $filename) {
            $title = $this->formatTitle(basename($filename, '.md'));
            $nav[] = [$title => $filename];
        }

        // Changelog/Release Notes
        $changelogNav = $this->buildChangelogNav($releaseNotes);
        if (!empty($changelogNav)) {
            $nav[] = ['Release Notes' => $changelogNav];
        }

        return $nav;
    }

    /**
     * Build Packages section navigation (listing + each package)
     * @param DiscoveredPackage[] $packages
     */
    private function buildPackagesNav(array $packages): array
    {
        $nav = [];

        // Packages listing/index page
        $packagesIndexPath = $this->targetDir . '/packages/index.md';
        if (file_exists($packagesIndexPath)) {
            $nav[] = ['Overview' => 'packages/index.md'];
        }

        // Each package as subsection
        foreach ($this->sortPackages($packages) as $package) {
            $packageNav = $this->buildPackageNav($package);
            if (!empty($packageNav)) {
                $nav[] = [$package->getTitle() => $packageNav];
            }
        }

        return $nav;
    }

    /**
     * Build navigation for a single package from its docs directory structure
     */
    private function buildPackageNav(DiscoveredPackage $package): array
    {
        $packageDir = $this->targetDir . '/' . $package->targetDir;
        if (!is_dir($packageDir)) {
            return [];
        }

        return $this->scanDirectoryForNav($packageDir, $package->targetDir);
    }

    /**
     * Recursively scan directory and build navigation
     */
    private function scanDirectoryForNav(string $dirPath, string $relativePath): array
    {
        $nav = [];
        $entries = scandir($dirPath);
        if ($entries === false) {
            return $nav;
        }

        // Separate files and directories
        $files = [];
        $dirs = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = $dirPath . '/' . $entry;
            if (is_file($entryPath) && str_ends_with($entry, '.md')) {
                $files[] = $entry;
            } elseif (is_dir($entryPath)) {
                $dirs[] = $entry;
            }
        }

        // Sort using metadata (_meta.yaml, front matter) with fallback to defaults
        $sorted = $this->metadata->sortItems($dirPath, $files, $dirs);
        $files = $sorted['files'];
        $dirs = $sorted['dirs'];

        // Add files
        foreach ($files as $file) {
            $title = $this->formatTitle(basename($file, '.md'));
            $path = $relativePath . '/' . $file;
            $nav[] = [$title => $path];
        }

        // Add subdirectories
        foreach ($dirs as $dir) {
            $subNav = $this->scanDirectoryForNav($dirPath . '/' . $dir, $relativePath . '/' . $dir);
            if (!empty($subNav)) {
                $dirTitle = $this->formatTitle($dir);
                $nav[] = [$dirTitle => $subNav];
            }
        }

        return $nav;
    }

    /**
     * Build cookbook navigation from example groups
     * @param ExampleGroup[] $exampleGroups
     */
    private function buildCookbookNav(array $exampleGroups): array
    {
        $nav = [];

        // Add intro pages if they exist
        foreach ($this->config->examplesIntroPages as $introPage) {
            $pagePath = $this->targetDir . '/' . $introPage;
            if (file_exists($pagePath)) {
                $title = $this->formatTitle(basename($introPage, '.md'));
                $nav[] = [$title => $introPage];
            }
        }

        // Add example groups
        foreach ($exampleGroups as $group) {
            $groupNav = [];
            foreach ($group->examples as $example) {
                if (!empty($example->tab)) {
                    $title = $example->hasTitle ? $example->title : $example->name;
                    $path = 'cookbook' . $example->toDocPath() . '.md';
                    $groupNav[] = [$title => $path];
                }
            }
            if (!empty($groupNav)) {
                $groupTitle = $group->title ?: $this->formatTitle($group->name);
                $nav[] = [$groupTitle => $groupNav];
            }
        }

        return $nav;
    }

    /**
     * Build changelog navigation from release notes
     */
    private function buildChangelogNav(array $releaseNotes): array
    {
        $nav = [];

        // Add overview if exists
        $overviewPath = $this->targetDir . '/release-notes/versions.md';
        if (file_exists($overviewPath)) {
            $nav[] = ['Overview' => 'release-notes/versions.md'];
        }

        // Add version entries (already sorted newest first)
        foreach ($releaseNotes as $release) {
            $nav[] = ['v' . $release['version'] => $release['path']];
        }

        return $nav;
    }

    /**
     * Sort packages according to configured order
     * @param DiscoveredPackage[] $packages
     * @return DiscoveredPackage[]
     */
    private function sortPackages(array $packages): array
    {
        $order = $this->config->packageOrder;

        usort($packages, function ($a, $b) use ($order) {
            $aIndex = array_search($a->name, $order, true);
            $bIndex = array_search($b->name, $order, true);

            // If both in order, sort by order
            if ($aIndex !== false && $bIndex !== false) {
                return (int) $aIndex - (int) $bIndex;
            }
            // If only a in order, a comes first
            if ($aIndex !== false) {
                return -1;
            }
            // If only b in order, b comes first
            if ($bIndex !== false) {
                return 1;
            }
            // Neither in order, sort alphabetically
            return strcmp($a->name, $b->name);
        });

        return $packages;
    }

    /**
     * Format title from filename or directory name
     */
    private function formatTitle(string $name): string
    {
        // Special cases
        $specialCases = [
            'index' => 'Overview',
            'api' => 'API',
            'llm' => 'LLM',
            'http' => 'HTTP',
            'cli' => 'CLI',
            'faq' => 'FAQ',
        ];

        $lower = strtolower($name);
        if (isset($specialCases[$lower])) {
            return $specialCases[$lower];
        }

        // Handle numbered prefixes like "1-overview", "01_setup", or "9-1-custom-clients"
        // Repeat to handle multiple prefixes (e.g., "9-1-" becomes "")
        $name = preg_replace('/^(\d+[-_])+/', '', $name) ?? $name;

        return ucwords(str_replace(['-', '_'], ' ', $name));
    }
}
