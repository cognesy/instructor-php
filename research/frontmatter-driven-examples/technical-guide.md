# Technical Implementation Guide: Frontmatter-Driven Examples

## Overview

This guide provides the specific code changes needed to implement frontmatter-driven example organization, allowing examples to live in `packages/*/examples/` directories while using YAML frontmatter to control documentation placement.

## Quick Implementation Path

### Step 1: Enhance ExampleInfo Class

**File:** `packages/hub/src/Data/ExampleInfo.php`

**Current State:** Already parses basic frontmatter (`title`, `docname`)
**Enhancement:** Add support for navigation-related frontmatter fields

```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

use Cognesy\Utils\Markdown\FrontMatter;
use Cognesy\Utils\Str;

class ExampleInfo
{
    public function __construct(
        public string $title,
        public string $docName,
        public string $content,
        // NEW FIELDS for navigation control
        public ?string $tab = null,
        public ?string $group = null,
        public ?string $groupTitle = null,
        public int $weight = 100,
        public array $tags = [],
        public ?string $package = null,
    ) {}

    public static function fromFile(string $path, string $name): ExampleInfo {
        [$content, $data] = self::yamlFrontMatter($path);

        return new ExampleInfo(
            title: $data['title'] ?? self::getTitle($content),
            docName: $data['docname'] ?? Str::snake($name),
            content: $content,
            // Parse new frontmatter fields
            tab: $data['tab'] ?? null,
            group: $data['group'] ?? null,
            groupTitle: $data['groupTitle'] ?? $data['group_title'] ?? null, // Support both formats
            weight: $data['weight'] ?? 100,
            tags: $data['tags'] ?? [],
            package: $data['package'] ?? null,
        );
    }

    // ... existing methods remain unchanged
}
```

### Step 2: Enhance Example Class for Package Detection

**File:** `packages/hub/src/Data/Example.php`

**Current State:** Uses hardcoded mapping array
**Enhancement:** Support frontmatter-driven navigation with package detection fallback

```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

use Cognesy\Auxiliary\Mintlify\NavigationItem;

class Example
{
    public function __construct(
        public int $index = 0,
        public string $tab = '',
        public string $group = '',
        public string $groupTitle = '',
        public string $name = '',
        public bool $hasTitle = false,
        public string $title = '',
        public string $docName = '',
        public string $content = '',
        public string $directory = '',
        public string $relativePath = '',
        public string $runPath = '',
        // NEW FIELDS
        public string $package = '',
        public int $weight = 100,
        public array $tags = [],
    ) {}

    public static function fromFile(string $baseDir, string $path, int $index = 0): static {
        [$group, $name] = explode('/', $path, 2);
        $info = ExampleInfo::fromFile($baseDir . $path . '/run.php', $name);

        // Detect package from base directory
        $package = self::detectPackageFromPath($baseDir);

        // Use frontmatter values with intelligent fallbacks
        $tab = $info->tab ?? self::detectTabFromPackageOrGroup($package, $group);
        $resolvedGroup = $info->group ?? self::detectGroupFromPath($group);
        $groupTitle = $info->groupTitle ?? self::generateGroupTitle($tab, $resolvedGroup, $group);

        return new Example(
            index: $index,
            tab: $tab,
            group: $resolvedGroup,
            groupTitle: $groupTitle,
            name: $name,
            hasTitle: $info->hasTitle(),
            title: $info->title,
            docName: $info->docName,
            content: $info->content,
            directory: $baseDir . $path,
            relativePath: './' . $tab . '/' . $path . '/run.php',
            runPath: $baseDir . $path . '/run.php',
            package: $package,
            weight: $info->weight,
            tags: $info->tags,
        );
    }

    // ... existing methods remain unchanged

    // NEW METHODS for package detection and fallbacks

    private static function detectPackageFromPath(string $baseDir): string {
        // Extract package name from path like "packages/instructor/examples"
        if (preg_match('#packages/([^/]+)/#', $baseDir, $matches)) {
            return $matches[1];
        }

        // Fallback: if in central examples directory, detect from group prefix
        return 'core';
    }

    private static function detectTabFromPackageOrGroup(string $package, string $group): string {
        // First try package-based detection
        $tabFromPackage = match($package) {
            'instructor' => 'instructor',
            'polyglot' => 'polyglot',
            'http-client' => 'http',
            default => null
        };

        if ($tabFromPackage) {
            return $tabFromPackage;
        }

        // Fallback to legacy group-based detection for central examples
        if (str_starts_with($group, 'A0')) return 'instructor';
        if (str_starts_with($group, 'B0')) return 'polyglot';
        if (str_starts_with($group, 'C0')) return 'prompting';

        return 'cookbook';
    }

    private static function detectGroupFromPath(string $legacyGroup): string {
        // Map legacy directory names to clean group names
        $legacyMapping = [
            'A01_Basics' => 'basics',
            'A02_Advanced' => 'advanced',
            'A03_Troubleshooting' => 'troubleshooting',
            'A04_APISupport' => 'api_support',
            'A05_Extras' => 'extras',
            'B01_LLM' => 'llm_basics',
            'B02_LLMAdvanced' => 'llm_advanced',
            'B03_LLMTroubleshooting' => 'llm_troubleshooting',
            'B04_LLMApiSupport' => 'llm_api_support',
            'B05_LLMExtras' => 'llm_extras',
            'C01_ZeroShot' => 'zero_shot',
            'C02_FewShot' => 'few_shot',
            'C03_ThoughtGen' => 'thought_gen',
            'C04_Ensembling' => 'ensembling',
            'C05_SelfCriticism' => 'self_criticism',
            'C06_Decomposition' => 'decomposition',
            'C07_Misc' => 'misc',
        ];

        return $legacyMapping[$legacyGroup] ?? strtolower($legacyGroup);
    }

    private static function generateGroupTitle(string $tab, string $group, string $legacyGroup): string {
        // Generate human-readable titles
        $titleMapping = [
            // Instructor
            'basics' => 'Cookbook \ Instructor \ Basics',
            'advanced' => 'Cookbook \ Instructor \ Advanced',
            'troubleshooting' => 'Cookbook \ Instructor \ Troubleshooting',
            'api_support' => 'Cookbook \ Instructor \ API Support',
            'extras' => 'Cookbook \ Instructor \ Extras',

            // Polyglot
            'llm_basics' => 'Cookbook \ Polyglot \ LLM Basics',
            'llm_advanced' => 'Cookbook \ Polyglot \ LLM Advanced',
            'llm_troubleshooting' => 'Cookbook \ Polyglot \ LLM Troubleshooting',
            'llm_api_support' => 'Cookbook \ Polyglot \ LLM API Support',
            'llm_extras' => 'Cookbook \ Polyglot \ LLM Extras',

            // Prompting
            'zero_shot' => 'Cookbook \ Prompting \ Zero-Shot Prompting',
            'few_shot' => 'Cookbook \ Prompting \ Few-Shot Prompting',
            'thought_gen' => 'Cookbook \ Prompting \ Thought Generation',
            'ensembling' => 'Cookbook \ Prompting \ Ensembling',
            'self_criticism' => 'Cookbook \ Prompting \ Self-Criticism',
            'decomposition' => 'Cookbook \ Prompting \ Decomposition',
            'misc' => 'Cookbook \ Prompting \ Miscellaneous',
        ];

        if (isset($titleMapping[$group])) {
            return $titleMapping[$group];
        }

        // Generate title dynamically
        $tabTitle = ucfirst($tab);
        $groupTitle = ucwords(str_replace('_', ' ', $group));
        return "Cookbook \ {$tabTitle} \ {$groupTitle}";
    }
}
```

### Step 3: Enhance ExampleRepository for Multi-Package Support

**File:** `packages/hub/src/Services/ExampleRepository.php`

**Current State:** Scans single `baseDir`
**Enhancement:** Support multiple package directories

```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Services;

use Cognesy\Config\BasePath;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Data\ExampleGroup;

class ExampleRepository {
    private array $baseDirs = [];

    // ENHANCED CONSTRUCTOR - now accepts multiple directories
    public function __construct(array $baseDirs = []) {
        $this->baseDirs = empty($baseDirs)
            ? $this->getDefaultDirectories()
            : $this->normalizeDirectories($baseDirs);
    }

    private function getDefaultDirectories(): array {
        $base = BasePath::get('');
        return [
            $base . 'examples',                          // Legacy central location
            $base . 'packages/instructor/examples',      // Package-specific
            $base . 'packages/polyglot/examples',        // Package-specific
            $base . 'packages/http-client/examples',     // Package-specific
        ];
    }

    private function normalizeDirectories(array $dirs): array {
        return array_map([$this, 'withEndingSlash'], array_filter($dirs, 'is_dir'));
    }

    // ... existing public methods remain unchanged

    // ENHANCED INTERNAL METHODS

    private function getExampleDirectories(): array {
        $allDirectories = [];

        foreach ($this->baseDirs as $baseDir) {
            $directories = $this->scanSingleDirectory($baseDir);
            // Prefix with base directory for unique identification
            foreach ($directories as $dir) {
                $allDirectories[] = $baseDir . $dir;
            }
        }

        return array_unique($allDirectories);
    }

    private function scanSingleDirectory(string $baseDir): array {
        if (!is_dir($baseDir)) {
            return [];
        }

        $files = $this->getSubdirectories('', $baseDir);
        $directories = [];

        foreach ($files as $file) {
            $fullPath = $baseDir . $file;
            if (!is_dir($fullPath)) {
                continue;
            }

            // Check if this directory has examples directly
            if (file_exists($fullPath . '/run.php')) {
                $directories[] = $file;
                continue;
            }

            // Check subdirectories for examples
            $subDirectories = $this->getSubdirectories($file, $baseDir);
            foreach ($subDirectories as $subDir) {
                if (file_exists($baseDir . $subDir . '/run.php')) {
                    $directories[] = $subDir;
                }
            }
        }

        return array_unique($directories);
    }

    private function getSubdirectories(string $path, string $baseDir = null): array {
        $fullPath = ($baseDir ?? $this->baseDirs[0]) . $path;
        $files = scandir($fullPath) ?: [];
        $files = array_diff($files, ['.', '..']);
        $directories = [];

        foreach ($files as $fileName) {
            if (is_dir($fullPath . '/' . $fileName)) {
                $directories[] = empty($path) ? $fileName : implode('/', [$path, $fileName]);
            }
        }

        return $directories;
    }

    private function getRunPath(string $path): string {
        // Find the run.php file across all base directories
        foreach ($this->baseDirs as $baseDir) {
            $runPath = $baseDir . $path . '/run.php';
            if (file_exists($runPath)) {
                return $runPath;
            }
        }

        // Fallback to first directory for error reporting
        return $this->baseDirs[0] . $path . '/run.php';
    }

    private function exampleExists(string $path): bool {
        foreach ($this->baseDirs as $baseDir) {
            if (file_exists($baseDir . $path . '/run.php')) {
                return true;
            }
        }
        return false;
    }

    private function getExample(string $path, int $index = 0): Example {
        // Find which base directory contains this example
        foreach ($this->baseDirs as $baseDir) {
            if (file_exists($baseDir . $path . '/run.php')) {
                return Example::fromFile($baseDir, $path, $index);
            }
        }

        // Fallback to first directory
        return Example::fromFile($this->baseDirs[0], $path, $index);
    }

    // ... rest of existing methods remain unchanged
}
```

### Step 4: Update Documentation System

**File:** `packages/doctor/src/Docs.php`

**Current State:** Single examples directory
**Enhancement:** Multi-package support

```php
private function registerServices(): void
{
    // Enhanced example repository with package support
    $exampleDirectories = [
        BasePath::get('examples'),                     // Legacy central directory
        BasePath::get('packages/instructor/examples'), // Instructor package
        BasePath::get('packages/polyglot/examples'),   // Polyglot package
        BasePath::get('packages/http-client/examples'), // HTTP package
        // Add more packages as needed
    ];

    $this->examples = new ExampleRepository($exampleDirectories);

    // ... rest of existing code remains unchanged
}
```

### Step 5: Enhanced Frontmatter Format

**Target Frontmatter Structure:** Add these fields to existing examples

```yaml
---
# Existing fields (keep as-is)
title: 'Basic use'
docname: 'basic_use'

# NEW FIELDS for navigation control
tab: 'instructor'                    # Target documentation tab (instructor|polyglot|prompting|http)
group: 'basics'                     # Group within tab (basics|advanced|troubleshooting|etc)
groupTitle: 'Basics'               # Human-readable group title (optional)
weight: 100                         # Order within group (optional, default: 100)
tags: ['beginner', 'core']          # Tags for filtering/search (optional)
package: 'instructor'               # Source package (optional, auto-detected)
---
```

## Quick Migration Script

**File:** `scripts/quick-frontmatter-update.php`

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Cognesy\Utils\Markdown\FrontMatter;

class QuickFrontmatterUpdater {
    private array $mappings = [
        'A01_Basics' => ['tab' => 'instructor', 'group' => 'basics', 'groupTitle' => 'Basics'],
        'A02_Advanced' => ['tab' => 'instructor', 'group' => 'advanced', 'groupTitle' => 'Advanced'],
        'B01_LLM' => ['tab' => 'polyglot', 'group' => 'llm_basics', 'groupTitle' => 'LLM Basics'],
        'C01_ZeroShot' => ['tab' => 'prompting', 'group' => 'zero_shot', 'groupTitle' => 'Zero-Shot Prompting'],
        // Add more as needed
    ];

    public function updateExample(string $examplePath): void {
        $runFile = $examplePath . '/run.php';
        if (!file_exists($runFile)) {
            echo "Skipping {$examplePath}: run.php not found\n";
            return;
        }

        $content = file_get_contents($runFile);
        $document = FrontMatter::parse($content);
        $frontmatter = $document->data();

        // Determine group from path
        $pathParts = explode('/', $examplePath);
        $groupDir = end($pathParts);
        while (prev($pathParts) && !isset($this->mappings[current($pathParts)]));
        $legacyGroup = current($pathParts) ?: $groupDir;

        if (isset($this->mappings[$legacyGroup])) {
            $mapping = $this->mappings[$legacyGroup];

            // Add navigation fields if not present
            $frontmatter['tab'] = $frontmatter['tab'] ?? $mapping['tab'];
            $frontmatter['group'] = $frontmatter['group'] ?? $mapping['group'];
            $frontmatter['groupTitle'] = $frontmatter['groupTitle'] ?? $mapping['groupTitle'];

            // Rebuild the file
            $yamlData = yaml_emit($frontmatter, YAML_UTF8_ENCODING);
            $newContent = "---\n" . trim($yamlData) . "\n---\n" . $document->document();

            file_put_contents($runFile, $newContent);
            echo "Updated: {$runFile}\n";
        } else {
            echo "No mapping for: {$legacyGroup} in {$examplePath}\n";
        }
    }

    public function updateAllExamples(): void {
        $examplesDir = __DIR__ . '/../examples';
        $directories = glob($examplesDir . '/*/*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $this->updateExample($dir);
        }
    }
}

$updater = new QuickFrontmatterUpdater();

if (isset($argv[1]) && $argv[1] === 'all') {
    $updater->updateAllExamples();
} elseif (isset($argv[1])) {
    $updater->updateExample($argv[1]);
} else {
    echo "Usage: php quick-frontmatter-update.php [path-to-example|all]\n";
    echo "Examples:\n";
    echo "  php quick-frontmatter-update.php examples/A01_Basics/Basic\n";
    echo "  php quick-frontmatter-update.php all\n";
}
```

## Testing the Implementation

### Step 1: Test Current System
```bash
# Verify current docs work
composer docs

# Test hub commands
composer hub list | head -10
```

### Step 2: Update Code with Enhancements
```bash
# Apply the code changes above
# Update ExampleInfo.php, Example.php, ExampleRepository.php, Docs.php
```

### Step 3: Test Enhanced System
```bash
# Test with existing structure (should work unchanged)
composer docs

# Add frontmatter to one example
echo '---
title: "Test Example"
docname: "test_example"
tab: "instructor"
group: "basics"
groupTitle: "Basics"
---

# Test Example
This is a test.' > examples/A01_Basics/Basic/run.php

# Test docs generation
composer docs
```

### Step 4: Create Package Examples
```bash
# Create package structure
mkdir -p packages/instructor/examples/basics/TestExample

# Create example with full frontmatter
echo '---
title: "Package Example"
docname: "package_example"
tab: "instructor"
group: "basics"
groupTitle: "Basics"
weight: 50
tags: ["test", "package"]
---

# Package Example
This example lives in the package.' > packages/instructor/examples/basics/TestExample/run.php

# Test docs generation
composer docs
```

## Benefits of This Implementation

### 1. **Immediate Benefits**
- Zero breaking changes to existing system
- Examples can be moved gradually
- Frontmatter provides full control over placement

### 2. **Future Benefits**
- Organic growth - new packages can add examples easily
- No hardcoded mappings to maintain
- Flexible reorganization via frontmatter updates

### 3. **Developer Experience**
- Clear package ownership of examples
- Examples live with related code
- Easy to find and maintain

### 4. **Documentation Quality**
- Consistent navigation structure
- Support for weights and ordering
- Tags for filtering and search

This technical implementation provides a pragmatic path to achieve frontmatter-driven, package-based examples while maintaining full backward compatibility.