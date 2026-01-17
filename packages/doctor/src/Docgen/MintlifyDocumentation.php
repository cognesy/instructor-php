<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen;

use Cognesy\Auxiliary\Mintlify\MintlifyIndex;
use Cognesy\Auxiliary\Mintlify\Navigation;
use Cognesy\Auxiliary\Mintlify\NavigationGroup;
use Cognesy\Auxiliary\Mintlify\NavigationItem;
use Cognesy\Config\BasePath;
use Cognesy\Doctor\Docgen\Data\DiscoveredPackage;
use Cognesy\Doctor\Docgen\Data\DocsConfig;
use Cognesy\Doctor\Docgen\Data\DocumentationConfig;
use Cognesy\Doctor\Docgen\Data\FileProcessingResult;
use Cognesy\Doctor\Docgen\Data\GenerationResult;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Files;

class MintlifyDocumentation
{
    private DocsConfig $docsConfig;
    private PackageDiscovery $packageDiscovery;
    private PackageDocsBuilder $packageBuilder;
    private DocsMetadata $metadata;

    public function __construct(
        private ExampleRepository $examples,
        private DocumentationConfig $config,
    ) {
        $this->docsConfig = DocsConfig::fromFile();
        $this->packageDiscovery = new PackageDiscovery(
            packagesDir: 'packages',
            descriptions: $this->docsConfig->packageDescriptions,
            targetDirs: $this->docsConfig->packageTargetDirs,
            internal: $this->docsConfig->packageInternal,
        );
        $this->packageBuilder = new PackageDocsBuilder(
            targetBaseDir: $this->docsConfig->mintlifyTarget,
            format: 'mintlify',
        );
        $this->metadata = new DocsMetadata();
    }

    public function generateAll(): GenerationResult {
        $startTime = microtime(true);
        $filesProcessed = 0;
        $filesCreated = 0;
        $filesUpdated = 0;
        $errors = [];

        try {
            $this->initializeBaseFiles();

            $packageResult = $this->generatePackageDocs();
            $exampleResult = $this->generateExampleDocs();

            $filesProcessed = $packageResult->filesProcessed + $exampleResult->filesProcessed;
            $filesCreated = $packageResult->filesCreated + $exampleResult->filesCreated;
            $filesUpdated = $packageResult->filesUpdated + $exampleResult->filesUpdated;
            $errors = [...$packageResult->errors, ...$exampleResult->errors];

            $duration = microtime(true) - $startTime;

            return GenerationResult::success(
                filesProcessed: $filesProcessed,
                filesCreated: $filesCreated,
                filesUpdated: $filesUpdated,
                duration: $duration,
                message: 'All documentation generated successfully',
            );

        } catch (\Throwable $e) {
            return GenerationResult::failure(
                errors: [$e->getMessage()],
                filesProcessed: $filesProcessed,
                duration: microtime(true) - $startTime,
                message: 'Documentation generation failed',
            );
        }
    }

    public function generatePackageDocs(): GenerationResult {
        $startTime = microtime(true);
        $filesProcessed = 0;
        $errors = [];

        try {
            $packages = $this->packageDiscovery->discover();

            foreach ($packages as $package) {
                $result = $this->packageBuilder->processPackage($package);
                $filesProcessed += $result->filesProcessed;
                if (!$result->isSuccess()) {
                    array_push($errors, ...$result->errors);
                }
            }

            $duration = microtime(true) - $startTime;

            return GenerationResult::success(
                filesProcessed: $filesProcessed,
                duration: $duration,
                message: 'Package documentation generated successfully',
            );

        } catch (\Throwable $e) {
            return GenerationResult::failure(
                errors: [$e->getMessage()],
                filesProcessed: $filesProcessed,
                duration: microtime(true) - $startTime,
                message: 'Package documentation generation failed',
            );
        }
    }

    public function generateExampleDocs(): GenerationResult {
        $startTime = microtime(true);
        $filesProcessed = 0;
        $filesCreated = 0;
        $filesUpdated = 0;
        $filesSkipped = 0;
        $errors = [];

        try {
            $result = $this->updateHubIndex();
            if (!$result->isSuccess()) {
                return GenerationResult::failure(
                    errors: $result->errors,
                    duration: microtime(true) - $startTime,
                    message: 'Failed to update hub index',
                );
            }

            $exampleGroups = $this->examples->getExampleGroups();
            foreach ($exampleGroups as $exampleGroup) {
                foreach ($exampleGroup->examples as $example) {
                    $processResult = $this->processExample($example);
                    $filesProcessed++;

                    if ($processResult->isSuccess()) {
                        match ($processResult->action) {
                            'created' => $filesCreated++,
                            'updated' => $filesUpdated++,
                            'skipped' => $filesSkipped++,
                        };
                    } else {
                        $errors[] = $processResult->message;
                    }
                }
            }

            $duration = microtime(true) - $startTime;

            return GenerationResult::success(
                filesProcessed: $filesProcessed,
                filesSkipped: $filesSkipped,
                filesCreated: $filesCreated,
                filesUpdated: $filesUpdated,
                duration: $duration,
                message: 'Example documentation generated successfully',
            );

        } catch (\Throwable $e) {
            return GenerationResult::failure(
                errors: [$e->getMessage()],
                filesProcessed: $filesProcessed,
                duration: microtime(true) - $startTime,
                message: 'Example documentation generation failed',
            );
        }
    }

    public function clearDocumentation(): GenerationResult {
        $startTime = microtime(true);

        try {
            Files::removeDirectory($this->config->docsTargetDir);

            return GenerationResult::success(
                duration: microtime(true) - $startTime,
                message: 'Documentation cleared successfully',
            );
        } catch (\Throwable $e) {
            return GenerationResult::failure(
                errors: [$e->getMessage()],
                duration: microtime(true) - $startTime,
                message: 'Failed to clear documentation',
            );
        }
    }

    public function initializeBaseFiles(): void {
        Files::removeDirectory($this->config->docsTargetDir);
        Files::copyDirectory($this->config->docsSourceDir, $this->config->docsTargetDir);
        Files::renameFileExtensions($this->config->docsTargetDir, 'md', 'mdx');

        // Generate packages listing page
        $this->generatePackagesIndex();
    }

    /**
     * Generate the packages listing/index page
     */
    private function generatePackagesIndex(): void {
        $packages = $this->packageDiscovery->discover();
        $sourceFile = $this->config->docsSourceDir . '/packages.md';
        $indexGenerator = new PackagesIndexGenerator($sourceFile);

        $targetPath = $this->config->docsTargetDir . '/packages/index.mdx';
        $indexGenerator->generateToFile($packages, $targetPath);
    }

    private function processExample(Example $example): FileProcessingResult {
        if (empty($example->tab)) {
            return FileProcessingResult::skipped($example->name, 'No tab specified');
        }

        try {
            $targetFilePath = $this->config->cookbookTargetDir . $example->toDocPath() . '.mdx';
            $sourceFileLastUpdate = filemtime($example->runPath);
            $targetFileExists = file_exists($targetFilePath);

            if ($targetFileExists) {
                $targetFileLastUpdate = filemtime($targetFilePath);
                if ($sourceFileLastUpdate > $targetFileLastUpdate) {
                    unlink($targetFilePath);
                    Files::copyFile($example->runPath, $targetFilePath);
                    return FileProcessingResult::updated($targetFilePath, 'Source file updated');
                }

                return FileProcessingResult::skipped($targetFilePath, 'No changes detected');
            }

            Files::copyFile($example->runPath, $targetFilePath);
            return FileProcessingResult::created($targetFilePath, 'New example file');

        } catch (\Throwable $e) {
            return FileProcessingResult::error($example->name, $e->getMessage(), $e);
        }
    }

    /**
     * Update mint.json with auto-generated navigation
     */
    private function updateHubIndex(): GenerationResult {
        try {
            $index = MintlifyIndex::fromFile($this->config->mintlifySourceIndexFile);
            if (!$index instanceof MintlifyIndex) {
                return GenerationResult::failure(['Failed to read hub index file']);
            }

            // Set up tabs: Main (primary), Packages, Cookbook
            $index->primaryTab = ['name' => 'Main'];
            $index->tabs = [
                ['name' => 'Packages', 'url' => 'packages'],
                ['name' => 'Cookbook', 'url' => 'cookbook'],
            ];

            // Build new navigation from autodiscovery
            $navigation = new Navigation();

            // 1. Main section (autodiscovered from docs root)
            $mainGroup = new NavigationGroup('Main');
            $targetDir = BasePath::get($this->docsConfig->mintlifyTarget);

            // Scan for mdx files in docs root
            $files = glob($targetDir . '/*.mdx') ?: [];

            // Sort files: index first, then alphabetically
            usort($files, function($a, $b) {
                $aName = basename($a);
                $bName = basename($b);
                if (str_starts_with($aName, 'index.')) return -1;
                if (str_starts_with($bName, 'index.')) return 1;
                return strcmp($aName, $bName);
            });

            foreach ($files as $file) {
                $pageName = basename($file, '.mdx');
                // Skip 'packages' - it belongs in Packages tab, not Main
                if ($pageName === 'packages') {
                    continue;
                }
                $mainGroup->pages[] = NavigationItem::fromString($pageName);
            }
            $navigation->appendGroup($mainGroup);

            // Add release notes as subgroup of Main
            $releaseNotesGroup = $this->getReleaseNotesGroup();
            $navigation->appendGroup($releaseNotesGroup);

            // 2. Packages section (listing + each package)
            $packagesGroup = new NavigationGroup('Packages');
            $packagesGroup->pages[] = NavigationItem::fromString('packages/index');
            $navigation->appendGroup($packagesGroup);

            $packages = $this->packageDiscovery->discover();
            $packages = $this->sortPackages($packages);
            foreach ($packages as $package) {
                $packageSubgroup = $this->buildPackageNavigationGroup($package);
                if (!empty($packageSubgroup->pages)) {
                    $navigation->appendGroup($packageSubgroup);
                }
            }

            // 3. Cookbook section (examples)
            $exampleGroups = $this->examples->getExampleGroups();
            foreach ($exampleGroups as $exampleGroup) {
                $navGroup = $exampleGroup->toNavigationGroup();
                $navigation->appendGroup($navGroup);
            }

            // Replace navigation
            $index->navigation = $navigation;

            $result = $index->saveFile($this->config->mintlifyTargetIndexFile);

            return $result
                ? GenerationResult::success(message: 'Hub index updated')
                : GenerationResult::failure(['Failed to save hub index file']);

        } catch (\Throwable $e) {
            return GenerationResult::failure([$e->getMessage()]);
        }
    }

    /**
     * Build navigation group for a package from its docs structure
     * Creates nested NavigationGroups for subdirectories to preserve hierarchy
     */
    private function buildPackageNavigationGroup(DiscoveredPackage $package): NavigationGroup {
        $group = new NavigationGroup($package->getTitle());
        $targetDir = BasePath::get($this->docsConfig->mintlifyTarget) . '/' . $package->targetDir;

        if (!is_dir($targetDir)) {
            return $group;
        }

        $items = $this->scanDirectoryHierarchically($targetDir, $package->targetDir);
        foreach ($items as $item) {
            if (is_string($item)) {
                $group->pages[] = NavigationItem::fromString($item);
            } else {
                // Nested group
                $group->pages[] = NavigationItem::fromArray(['group' => $item]);
            }
        }

        return $group;
    }

    /**
     * Recursively scan directory and return hierarchical structure
     * Returns array of: string (page path) or array (nested group with 'group' and 'pages' keys)
     */
    private function scanDirectoryHierarchically(string $dirPath, string $relativePath): array {
        $items = [];
        $entries = scandir($dirPath);
        if ($entries === false) {
            return $items;
        }

        // Separate files and directories
        $files = [];
        $dirs = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = $dirPath . '/' . $entry;
            if (is_file($entryPath) && str_ends_with($entry, '.mdx')) {
                $files[] = $entry;
            } elseif (is_dir($entryPath)) {
                $dirs[] = $entry;
            }
        }

        // Sort using metadata (_meta.yaml, front matter) with fallback to defaults
        $sorted = $this->metadata->sortItems($dirPath, $files, $dirs);
        $files = $sorted['files'];
        $dirs = $sorted['dirs'];

        // Add files at this level (without .mdx extension for Mintlify)
        foreach ($files as $file) {
            $pagePath = $relativePath . '/' . basename($file, '.mdx');
            $items[] = $pagePath;
        }

        // Add subdirectories as nested groups
        foreach ($dirs as $dir) {
            $subDirPath = $dirPath . '/' . $dir;
            $subRelativePath = $relativePath . '/' . $dir;
            $subItems = $this->scanDirectoryHierarchically($subDirPath, $subRelativePath);

            if (!empty($subItems)) {
                // Create nested group with formatted directory name as title
                $groupName = $this->formatDirectoryName($dir);
                $items[] = [
                    'group' => $groupName,
                    'pages' => $this->flattenNestedItems($subItems),
                ];
            }
        }

        return $items;
    }

    /**
     * Flatten nested items for Mintlify (it only supports one level of nesting in groups)
     */
    private function flattenNestedItems(array $items): array {
        $flattened = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $flattened[] = $item;
            } else {
                // For deeper nesting, flatten the group pages
                if (isset($item['pages'])) {
                    foreach ($item['pages'] as $page) {
                        $flattened[] = $page;
                    }
                }
            }
        }
        return $flattened;
    }

    /**
     * Format directory name as a readable title
     */
    private function formatDirectoryName(string $dir): string {
        // Convert kebab-case or snake_case to Title Case
        $name = str_replace(['-', '_'], ' ', $dir);
        return ucwords($name);
    }

    /**
     * Sort packages according to configured order
     * @param DiscoveredPackage[] $packages
     * @return DiscoveredPackage[]
     */
    private function sortPackages(array $packages): array {
        $order = $this->docsConfig->packageOrder;

        usort($packages, function ($a, $b) use ($order) {
            $aIndex = array_search($a->name, $order, true);
            $bIndex = array_search($b->name, $order, true);

            if ($aIndex !== false && $bIndex !== false) {
                return (int) $aIndex - (int) $bIndex;
            }
            if ($aIndex !== false) {
                return -1;
            }
            if ($bIndex !== false) {
                return 1;
            }
            return strcmp($a->name, $b->name);
        });

        return $packages;
    }

    private function getReleaseNotesGroup(): NavigationGroup {
        $releaseNotesDir = BasePath::get($this->docsConfig->mintlifyTarget) . '/release-notes';
        $releaseNotesFiles = glob($releaseNotesDir . '/*.mdx') ?: [];
        $pages = [];

        foreach ($releaseNotesFiles as $releaseNotesFile) {
            $fileName = pathinfo($releaseNotesFile, PATHINFO_FILENAME);
            if ($fileName !== 'versions') {
                $pages[] = str_replace('v', '', $fileName);
            }
        }

        usort($pages, fn($a, $b) => version_compare($b, $a));

        $releaseNotesGroup = new NavigationGroup('Release Notes');
        $releaseNotesGroup->pages[] = NavigationItem::fromString('release-notes/versions');
        foreach ($pages as $page) {
            $releaseNotesGroup->pages[] = NavigationItem::fromString('release-notes/v' . $page);
        }

        return $releaseNotesGroup;
    }
}
