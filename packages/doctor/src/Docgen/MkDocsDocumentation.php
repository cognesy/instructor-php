<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen;

use Cognesy\Config\BasePath;
use Cognesy\Doctor\Docgen\Data\DocumentationConfig;
use Cognesy\Doctor\Docgen\Data\FileProcessingResult;
use Cognesy\Doctor\Docgen\Data\GenerationResult;
use Cognesy\Doctor\Markdown\MarkdownFile;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Files;
use Symfony\Component\Yaml\Yaml;

class MkDocsDocumentation
{
    public function __construct(
        private ExampleRepository $examples,
        private DocumentationConfig $config,
    ) {}

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
            $packages = ['instructor', 'polyglot', 'http-client', 'laravel'];
            $targetDirs = [$this->config->docsTargetDir . '/instructor', $this->config->docsTargetDir . '/polyglot', $this->config->docsTargetDir . '/http', $this->config->docsTargetDir . '/laravel'];

            foreach (array_combine($packages, $targetDirs) as $package => $targetDir) {
                $result = $this->processPackage($package, $targetDir);
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

    private function processPackage(string $package, string $targetDir): GenerationResult {
        $startTime = microtime(true);
        $filesProcessed = 0;

        try {
            $sourcePath = BasePath::get("packages/$package/docs");
            $targetPath = BasePath::get($targetDir);

            Files::removeDirectory($targetPath);
            Files::copyDirectory($sourcePath, $targetPath);
            // Keep .md extensions for MkDocs

            $this->inlineExternalCodeblocks($targetPath, $package);
            $filesProcessed++;

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

    public function updateMkDocsConfig(): GenerationResult {
        try {
            // Read template from source docs directory (not the generated one)
            $templatePath = $this->config->docsSourceDir . '/mkdocs.yml.template';
            $configPath = dirname($this->config->docsTargetDir) . '/mkdocs.yml';
            
            // Load template or create basic config
            if (file_exists($templatePath)) {
                // Read template content and preprocess Python tags
                $templateContent = file_get_contents($templatePath);
                if ($templateContent === false) {
                    return GenerationResult::failure(['Failed to read template file']);
                }
                $templateContent = $this->preprocessPythonTags($templateContent);
                $config = Yaml::parse($templateContent);
            } else {
                $config = $this->getDefaultMkDocsConfig();
            }

            if ($config === false || $config === null) {
                return GenerationResult::failure(['Failed to parse MkDocs template']);
            }

            assert(is_array($config));

            // Add dynamic release notes section to navigation
            $config = $this->addReleaseNotesToNavigation($config);

            // Use template navigation structure as-is - user has crafted it intentionally
            // No filtering needed since template should only reference existing files

            $yamlContent = Yaml::dump($config, 4, 2);

            // Ensure target directory exists
            $targetDir = dirname($configPath);
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $targetDir));
                }
            }

            $result = file_put_contents($configPath, $yamlContent);

            return $result !== false
                ? GenerationResult::success(message: 'MkDocs config created successfully')
                : GenerationResult::failure(['Failed to save MkDocs config file']);

        } catch (\Throwable $e) {
            return GenerationResult::failure([$e->getMessage()]);
        }
    }

    /** @phpstan-ignore-next-line */
    private function buildNavigation(array $baseNav, array $exampleGroups): array {
        // Remove existing dynamic sections if any
        $staticNav = array_filter($baseNav, function($item) {
            if (is_array($item)) {
                $key = array_key_first($item);
                return !in_array($key, $this->config->dynamicGroups, true);
            }
            return true;
        });

        // Add example groups
        foreach ($exampleGroups as $exampleGroup) {
            /** @var \Cognesy\InstructorHub\Data\ExampleGroup $exampleGroup */
            $groupNav = [$exampleGroup->name => []];

            foreach ($exampleGroup->examples as $example) {
                /** @var \Cognesy\InstructorHub\Data\Example $example */
                if (!empty($example->tab)) {
                    $title = $example->hasTitle ? $example->title : $example->name;
                    $path = 'cookbook' . $example->toDocPath() . '.md';
                    $groupNav[$exampleGroup->name][] = [$title => $path];
                }
            }
            
            if (!empty($groupNav[$exampleGroup->name])) {
                $staticNav[] = $groupNav;
            }
        }

        return $staticNav;
    }

    /** @phpstan-ignore-next-line */
    private function buildNavigationFromStructure(): array {
        // Basic navigation structure - can be expanded
        $nav = [];
        
        // Add home
        $nav[] = ['Home' => 'index.md'];
        
        // Add package documentation sections
        $packageNav = $this->buildPackageDocNavigation();
        foreach ($packageNav as $packageSection) {
            $nav[] = $packageSection;
        }
        
        // Add cookbook sections
        $cookbookNav = $this->buildCookbookNavigation();
        if (!empty($cookbookNav)) {
            $nav[] = ['Cookbook' => $cookbookNav];
        }
        
        return $nav;
    }

    private function buildPackageDocNavigation(): array {
        $nav = [];
        $packages = [
            'instructor' => 'Instructor',
            'polyglot' => 'Polyglot', 
            'http' => 'HTTP Client'
        ];
        
        foreach ($packages as $packageDir => $packageTitle) {
            $packagePath = $this->config->docsTargetDir . '/' . $packageDir;
            if (is_dir($packagePath)) {
                $packageNav = $this->buildDirectoryNavigation($packagePath, $packageDir);
                if (!empty($packageNav)) {
                    $nav[] = [$packageTitle => $packageNav];
                }
            }
        }
        
        return $nav;
    }

    private function buildDirectoryNavigation(string $dirPath, string $relativeBasePath): array {
        $nav = [];
        $entries = scandir($dirPath);
        if ($entries === false) {
            return $nav;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            
            $entryPath = $dirPath . '/' . $entry;
            if (is_file($entryPath) && str_ends_with($entry, '.md')) {
                // Direct .md files
                $title = $this->formatFileTitle(basename($entry, '.md'));
                $nav[] = [$title => $relativeBasePath . '/' . $entry];
            } elseif (is_dir($entryPath)) {
                // Subdirectories
                $subNav = $this->buildDirectoryNavigation($entryPath, $relativeBasePath . '/' . $entry);
                if (!empty($subNav)) {
                    $dirTitle = $this->formatCategoryTitle($entry);
                    $nav[] = [$dirTitle => $subNav];
                }
            }
        }
        
        return $nav;
    }

    private function buildCookbookNavigation(): array {
        $cookbookDir = $this->config->docsTargetDir . '/cookbook';
        if (!is_dir($cookbookDir)) {
            return [];
        }

        $nav = [];
        $entries = scandir($cookbookDir);
        if ($entries === false) {
            return $nav;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            
            $entryPath = $cookbookDir . '/' . $entry;
            if (is_file($entryPath) && str_ends_with($entry, '.md')) {
                // Top-level md files
                $title = ucwords(str_replace(['-', '_'], ' ', basename($entry, '.md')));
                $nav[] = [$title => 'cookbook/' . $entry];
            } elseif (is_dir($entryPath)) {
                // Package directories
                $packageNav = $this->buildPackageNavigation($entryPath);
                if (!empty($packageNav)) {
                    $packageTitle = ucfirst($entry);
                    $nav[] = [$packageTitle => $packageNav];
                }
            }
        }

        return $nav;
    }

    private function buildPackageNavigation(string $packageDir): array {
        $nav = [];
        $entries = scandir($packageDir);
        if ($entries === false) {
            return $nav;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            
            $entryPath = $packageDir . '/' . $entry;
            if (is_dir($entryPath)) {
                // Category directories
                $categoryNav = $this->buildCategoryNavigation($entryPath);
                if (!empty($categoryNav)) {
                    $categoryTitle = $this->formatCategoryTitle($entry);
                    $nav[] = [$categoryTitle => $categoryNav];
                }
            }
        }

        return $nav;
    }

    private function buildCategoryNavigation(string $categoryDir): array {
        $nav = [];
        $entries = scandir($categoryDir);
        if ($entries === false) {
            return $nav;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || !str_ends_with($entry, '.md')) continue;
            
            $title = $this->formatFileTitle(basename($entry, '.md'));
            $relativePath = str_replace($this->config->docsTargetDir . '/', '', $categoryDir . '/' . $entry);
            $nav[] = [$title => $relativePath];
        }

        return $nav;
    }

    private function formatCategoryTitle(string $categoryName): string {
        return match($categoryName) {
            'api_support' => 'API Support',
            'llm_basics' => 'LLM Basics', 
            'llm_advanced' => 'LLM Advanced',
            'llm_api_support' => 'LLM API Support',
            'llm_troubleshooting' => 'LLM Troubleshooting',
            'llm_extras' => 'LLM Extras',
            'zero_shot' => 'Zero Shot',
            'few_shot' => 'Few Shot', 
            'thought_gen' => 'Thought Generation',
            default => ucwords(str_replace('_', ' ', $categoryName))
        };
    }

    private function formatFileTitle(string $fileName): string {
        return ucwords(str_replace('_', ' ', $fileName));
    }

    private function getDefaultMkDocsConfig(): array {
        return [
            'site_name' => 'Instructor for PHP',
            'site_author' => 'Dariusz Debowczyk',
            'theme' => ['name' => 'material'],
            'nav' => []
        ];
    }

    private function preprocessPythonTags(string $yamlContent): string {
        // Convert Python-specific YAML tags to strings that Symfony YAML can handle
        $result = preg_replace('/!!python\/name:([^\s]+)/', '"$1"', $yamlContent);
        if ($result === null) {
            return $yamlContent;
        }
        $yamlContent = $result;

        // Remove problematic emoji extension that requires Python functions
        $result = preg_replace('/\s*-\s*pymdownx\.emoji:.*?(?=\n\s*-|\n[^\s]|$)/s', '', $yamlContent);
        if ($result === null) {
            return $yamlContent;
        }

        // Just return the preprocessed content - let template control plugins/extra sections
        return $result;
    }

    /** @phpstan-ignore-next-line */
    private function filterExistingFiles(array $nav): array {
        return array_filter(array_map(function($item) {
            if (!is_array($item)) {
                return $item;
            }
            
            foreach ($item as $title => $content) {
                if (is_string($content)) {
                    $filePath = $this->config->docsTargetDir . '/' . $content;
                    return file_exists($filePath) ? $item : null;
                }
                
                if (is_array($content)) {
                    $filtered = $this->filterExistingFiles($content);
                    return !empty($filtered) ? [$title => $filtered] : null;
                }
            }
            
            return $item;
        }, $nav));
    }

    /** @phpstan-ignore-next-line */
    private function convertDictToList(array $dict): array {
        $list = [];
        foreach ($dict as $key => $value) {
            if (is_array($value)) {
                $list[] = [$key => $this->convertDictToList($value)];
            } else {
                $list[] = [$key => $value];
            }
        }
        return $list;
    }

    private function inlineExternalCodeblocks(string $targetPath, string $subpackage): void {
        $docFiles = array_merge(
            glob("$targetPath/*.md") ?: [],
            glob("$targetPath/**/*.md") ?: [],
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
            
            // Calculate relative path to images directory
            $imagePrefix = $depth > 0 ? str_repeat('../', $depth) . 'images/' : 'images/';
            
            // Replace absolute image paths with relative ones
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
                continue; // Skip the overview file
            }

            // Extract version from filename (e.g., v1.4.0, v1.0.0-RC22)
            if (preg_match('/^v(.+)$/', $filename, $matches)) {
                $versions[] = [
                    'version' => $matches[1],
                    'filename' => $filename,
                    'path' => 'release-notes/' . $filename . '.md'
                ];
            }
        }

        // Sort versions in descending order (newest first)
        usort($versions, function($a, $b) {
            return $this->compareVersions($b['version'], $a['version']);
        });

        return $versions;
    }

    private function compareVersions(string $version1, string $version2): int {
        // Handle release candidates and pre-release versions
        $normalize = function($version) {
            // Convert to lowercase for consistent comparison
            $version = strtolower($version);
            
            // Split version and pre-release parts
            if (preg_match('/^(\d+\.\d+\.\d+)(.*)$/', $version, $matches)) {
                $baseVersion = $matches[1];
                $preRelease = $matches[2];
                
                // Assign priority: stable > rc > other pre-releases
                $priority = 1000; // stable release
                if (str_contains($preRelease, 'rc')) {
                    $priority = 100;
                    // Extract RC number for proper sorting
                    if (preg_match('/rc(\d+)/', $preRelease, $rcMatches)) {
                        $priority += (int)$rcMatches[1];
                    }
                } elseif (!empty($preRelease)) {
                    $priority = 50; // other pre-releases (alpha, beta, etc.)
                }
                
                return $baseVersion . '.' . str_pad((string)$priority, 4, '0', STR_PAD_LEFT);
            }
            
            return $version;
        };

        return version_compare($normalize($version1), $normalize($version2));
    }

    private function buildReleaseNotesNavigation(): array {
        $releaseNotes = $this->scanReleaseNotes();
        
        if (empty($releaseNotes)) {
            return [];
        }

        $navigation = [];
        
        // Add overview first if it exists
        $versionsPath = $this->config->docsTargetDir . '/release-notes/versions.md';
        if (file_exists($versionsPath)) {
            $navigation[] = ['Overview' => 'release-notes/versions.md'];
        }

        // Add all version entries
        foreach ($releaseNotes as $release) {
            $navigation[] = ['v' . $release['version'] => $release['path']];
        }

        return $navigation;
    }

    private function addReleaseNotesToNavigation(array $config): array {
        if (!isset($config['nav']) || !is_array($config['nav'])) {
            return $config;
        }

        $releaseNotesNav = $this->buildReleaseNotesNavigation();
        if (empty($releaseNotesNav)) {
            return $config;
        }

        // Add the Changelog section after the existing navigation items
        $config['nav'][] = ['Changelog' => $releaseNotesNav];

        return $config;
    }
}