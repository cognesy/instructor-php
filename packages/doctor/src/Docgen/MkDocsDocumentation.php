<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen;

use Cognesy\Config\BasePath;
use Cognesy\Doctor\Docgen\Data\DocsConfig;
use Cognesy\Doctor\Docgen\Data\DocumentationConfig;
use Cognesy\Doctor\Docgen\Data\FileProcessingResult;
use Cognesy\Doctor\Docgen\Data\GenerationResult;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Files;
use Symfony\Component\Yaml\Yaml;

class MkDocsDocumentation
{
    private DocsConfig $docsConfig;
    private PackageDiscovery $packageDiscovery;
    private PackageDocsBuilder $packageBuilder;
    private NavigationBuilder $navBuilder;

    public function __construct(
        private ExampleRepository $examples,
        private DocumentationConfig $config,
    ) {
        $this->docsConfig = DocsConfig::fromFile();
        $this->packageDiscovery = new PackageDiscovery(
            packagesDir: 'packages',
            descriptions: $this->docsConfig->packageDescriptions,
            targetDirs: $this->docsConfig->packageTargetDirs,
        );
        $this->packageBuilder = new PackageDocsBuilder(
            targetBaseDir: $this->docsConfig->mkdocsTarget,
            format: 'mkdocs',
        );
        $this->navBuilder = new NavigationBuilder(
            config: $this->docsConfig,
            targetDir: BasePath::get($this->docsConfig->mkdocsTarget),
            format: 'mkdocs',
        );
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

            // Generate packages listing page
            $this->generatePackagesIndex();

            // Fix absolute image paths after all files are copied
            $this->fixImagePaths();

            $filesProcessed = $packageResult->filesProcessed + $exampleResult->filesProcessed;
            $filesCreated = $packageResult->filesCreated + $exampleResult->filesCreated;
            $filesUpdated = $packageResult->filesUpdated + $exampleResult->filesUpdated;
            $errors = [...$packageResult->errors, ...$exampleResult->errors];

            $this->updateMkDocsConfig();

            $duration = microtime(true) - $startTime;

            return GenerationResult::success(
                filesProcessed: $filesProcessed,
                filesCreated: $filesCreated,
                filesUpdated: $filesUpdated,
                duration: $duration,
                message: 'All MkDocs documentation generated successfully',
            );

        } catch (\Throwable $e) {
            return GenerationResult::failure(
                errors: [$e->getMessage()],
                filesProcessed: $filesProcessed,
                duration: microtime(true) - $startTime,
                message: 'MkDocs documentation generation failed',
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
                message: 'MkDocs documentation cleared successfully',
            );
        } catch (\Throwable $e) {
            return GenerationResult::failure(
                errors: [$e->getMessage()],
                duration: microtime(true) - $startTime,
                message: 'Failed to clear MkDocs documentation',
            );
        }
    }

    public function initializeBaseFiles(): void {
        Files::removeDirectory($this->config->docsTargetDir);
        Files::copyDirectory($this->config->docsSourceDir, $this->config->docsTargetDir);

        // Convert .mdx files to .md files for MkDocs (skeleton files from docs/)
        Files::renameFileExtensions($this->config->docsTargetDir, 'mdx', 'md');

        // Remove files that shouldn't be in the generated directory
        $filesToRemove = [
            $this->config->docsTargetDir . '/mint.json',              // Mintlify config
            $this->config->docsTargetDir . '/mkdocs.yml.template',    // Template (not final config)
        ];

        foreach ($filesToRemove as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Generate the packages listing/index page
     */
    private function generatePackagesIndex(): void {
        $packages = $this->packageDiscovery->discover();
        $sourceFile = $this->config->docsSourceDir . '/packages.md';
        $indexGenerator = new PackagesIndexGenerator($sourceFile);

        $targetPath = $this->config->docsTargetDir . '/packages/index.md';
        $indexGenerator->generateToFile($packages, $targetPath);
    }

    private function processExample(Example $example): FileProcessingResult {
        if (empty($example->tab)) {
            return FileProcessingResult::skipped($example->name, 'No tab specified');
        }

        try {
            $targetFilePath = $this->config->cookbookTargetDir . $example->toDocPath() . '.md';
            $sourceFileLastUpdate = filemtime($example->runPath);
            $targetFileExists = file_exists($targetFilePath);

            if ($targetFileExists) {
                $targetFileLastUpdate = filemtime($targetFilePath);
                if ($sourceFileLastUpdate > $targetFileLastUpdate) {
                    unlink($targetFilePath);
                    Files::copyFile($example->runPath, $targetFilePath);
                    return FileProcessingResult::updated($targetFilePath, 'Source file updated');
                } else {
                    return FileProcessingResult::skipped($targetFilePath, 'No changes detected');
                }
            } else {
                Files::copyFile($example->runPath, $targetFilePath);
                return FileProcessingResult::created($targetFilePath, 'New example file');
            }

        } catch (\Throwable $e) {
            return FileProcessingResult::error($example->name, $e->getMessage(), $e);
        }
    }

    /**
     * Build MkDocs config with auto-generated navigation
     */
    public function updateMkDocsConfig(): GenerationResult {
        try {
            $templatePath = BasePath::get($this->docsConfig->mkdocsTemplate);
            $configPath = dirname(BasePath::get($this->docsConfig->mkdocsTarget)) . '/mkdocs.yml';

            // Load template for theme/plugins config (ignoring its nav)
            $config = $this->loadTemplateConfig($templatePath);

            // Build navigation from autodiscovery
            $packages = $this->packageDiscovery->discover();
            $exampleGroups = $this->examples->getExampleGroups();
            $releaseNotes = $this->scanReleaseNotes();

            // Generate nav using NavigationBuilder
            $config['nav'] = $this->navBuilder->buildMkDocsNav($packages, $exampleGroups, $releaseNotes);

            // Write config
            $yamlContent = Yaml::dump($config, 6, 2);

            $targetDir = dirname($configPath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $result = file_put_contents($configPath, $yamlContent);

            return $result !== false
                ? GenerationResult::success(message: 'MkDocs config created successfully')
                : GenerationResult::failure(['Failed to save MkDocs config file']);

        } catch (\Throwable $e) {
            return GenerationResult::failure([$e->getMessage()]);
        }
    }

    /**
     * Load template config, extracting theme/plugins but ignoring nav
     */
    private function loadTemplateConfig(string $templatePath): array {
        if (!file_exists($templatePath)) {
            return $this->getDefaultMkDocsConfig();
        }

        $content = file_get_contents($templatePath);
        if ($content === false) {
            return $this->getDefaultMkDocsConfig();
        }

        // Preprocess Python tags
        $content = $this->preprocessPythonTags($content);
        $config = Yaml::parse($content);

        if (!is_array($config)) {
            return $this->getDefaultMkDocsConfig();
        }

        // Remove nav from template - we'll generate it
        unset($config['nav']);

        return $config;
    }

    private function getDefaultMkDocsConfig(): array {
        return [
            'site_name' => 'Instructor for PHP',
            'site_author' => 'Dariusz Debowczyk',
            'theme' => [
                'name' => 'material',
                'palette' => [
                    'scheme' => 'slate',
                    'primary' => 'deep orange',
                    'accent' => 'orange',
                ],
            ],
        ];
    }

    private function preprocessPythonTags(string $yamlContent): string {
        // Convert Python-specific YAML tags to strings
        $result = preg_replace('/!!python\/name:([^\s]+)/', '"$1"', $yamlContent);
        if ($result === null) {
            return $yamlContent;
        }
        $yamlContent = $result;

        // Remove emoji extension that requires Python functions
        $result = preg_replace('/\s*-\s*pymdownx\.emoji:.*?(?=\n\s*-|\n[^\s]|$)/s', '', $yamlContent);

        return $result ?? $yamlContent;
    }

    private function fixImagePaths(): void {
        $markdownFiles = array_merge(
            glob($this->config->docsTargetDir . '/*.md') ?: [],
            glob($this->config->docsTargetDir . '/**/*.md') ?: [],
            glob($this->config->docsTargetDir . '/**/**/*.md') ?: [],
            glob($this->config->docsTargetDir . '/**/**/**/*.md') ?: []
        );

        foreach ($markdownFiles as $filePath) {
            $content = file_get_contents($filePath);
            if ($content === false) {
                continue;
            }
            $relativePath = str_replace($this->config->docsTargetDir . '/', '', $filePath);
            $depth = substr_count($relativePath, '/');

            $imagePrefix = $depth > 0 ? str_repeat('../', $depth) . 'images/' : 'images/';

            $result = preg_replace('/(?<!["\'])(\/images\/)/', $imagePrefix, $content);
            if ($result !== null) {
                $content = $result;
            }

            file_put_contents($filePath, $content);
        }
    }

    private function scanReleaseNotes(): array {
        $releaseNotesDir = $this->config->docsTargetDir . '/release-notes';
        if (!is_dir($releaseNotesDir)) {
            return [];
        }

        $files = glob($releaseNotesDir . '/*.md') ?: [];
        $versions = [];

        foreach ($files as $file) {
            $filename = basename($file, '.md');
            if ($filename === 'versions') {
                continue;
            }

            if (preg_match('/^v(.+)$/', $filename, $matches)) {
                $versions[] = [
                    'version' => $matches[1],
                    'filename' => $filename,
                    'path' => 'release-notes/' . $filename . '.md'
                ];
            }
        }

        usort($versions, function($a, $b) {
            return $this->compareVersions($b['version'], $a['version']);
        });

        return $versions;
    }

    private function compareVersions(string $version1, string $version2): int {
        $normalize = function($version) {
            $version = strtolower($version);

            if (preg_match('/^(\d+\.\d+\.\d+)(.*)$/', $version, $matches)) {
                $baseVersion = $matches[1];
                $preRelease = $matches[2];

                $priority = 1000;
                if (str_contains($preRelease, 'rc')) {
                    $priority = 100;
                    if (preg_match('/rc(\d+)/', $preRelease, $rcMatches)) {
                        $priority += (int)$rcMatches[1];
                    }
                } elseif (!empty($preRelease)) {
                    $priority = 50;
                }

                return $baseVersion . '.' . str_pad((string)$priority, 4, '0', STR_PAD_LEFT);
            }

            return $version;
        };

        return version_compare($normalize($version1), $normalize($version2));
    }
}
