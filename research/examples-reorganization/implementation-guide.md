# Implementation Guide: Examples Reorganization

## Overview

This document provides step-by-step implementation details for reorganizing examples from the centralized `./examples` directory to package-specific `packages/*/examples` directories.

## Prerequisites

- Understanding of current documentation build process
- Familiarity with PHP namespace structure
- Access to project repository with write permissions

## Implementation Plan

### Phase 1: Infrastructure Setup

#### 1.1 Create Package Examples Directories

```bash
# Create examples directories in packages
mkdir -p packages/instructor/examples
mkdir -p packages/polyglot/examples

# Optional: Create prompting package if examples warrant separate package
# mkdir -p packages/prompting/examples
```

#### 1.2 Enhance ExampleRepository

**File:** `packages/hub/src/Services/ExampleRepository.php`

**Changes Required:**

1. **Constructor modification** to accept multiple base directories:

```php
// Current
public function __construct(string $baseDir) {
    $this->baseDir = $this->withEndingSlash($baseDir ?: ($this->guessBaseDir() . '/'));
}

// Enhanced
private array $baseDirs = [];

public function __construct(array $baseDirs = []) {
    if (empty($baseDirs)) {
        $baseDirs = [$this->guessBaseDir()];
    }
    $this->baseDirs = array_map([$this, 'withEndingSlash'], $baseDirs);
}
```

2. **Multi-directory scanning**:

```php
private function getExampleDirectories(): array {
    $allDirectories = [];

    foreach ($this->baseDirs as $baseDir) {
        $directories = $this->scanSingleDirectory($baseDir);
        $allDirectories = array_merge($allDirectories, $directories);
    }

    return array_unique($allDirectories);
}

private function scanSingleDirectory(string $baseDir): array {
    // Extract existing directory scanning logic here
    $files = $this->getSubdirectories('', $baseDir);
    $directories = [];

    foreach ($files as $file) {
        if (!is_dir($baseDir . '/' . $file)) {
            continue;
        }
        $subDirectories = $this->getSubdirectories($file, $baseDir);
        $directories = array_merge($directories, $subDirectories);
    }

    return $directories;
}
```

3. **Update methods that use baseDir**:

```php
private function getRunPath(string $path): string {
    // Try each base directory until file found
    foreach ($this->baseDirs as $baseDir) {
        $runPath = $baseDir . $path . '/run.php';
        if (file_exists($runPath)) {
            return $runPath;
        }
    }

    // Fallback to first directory for backward compatibility
    return $this->baseDirs[0] . $path . '/run.php';
}

private function exampleExists(string $path): bool {
    foreach ($this->baseDirs as $baseDir) {
        $runPath = $baseDir . $path . '/run.php';
        if (file_exists($runPath)) {
            return true;
        }
    }
    return false;
}
```

#### 1.3 Update Docs Application Configuration

**File:** `packages/doctor/src/Docs.php`

**Modify registerServices method:**

```php
private function registerServices(): void
{
    // Enhanced example repository with multiple directories
    $exampleDirectories = [
        BasePath::get('examples'), // Current centralized location
        BasePath::get('packages/instructor/examples'), // Package-specific
        BasePath::get('packages/polyglot/examples'),   // Package-specific
    ];

    $this->examples = new ExampleRepository($exampleDirectories);

    // ... rest of existing code
}
```

#### 1.4 Create Example Aggregation Command

**File:** `packages/doctor/src/Docgen/Commands/AggregateExamplesCommand.php`

```php
<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen\Commands;

use Cognesy\Config\BasePath;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class AggregateExamplesCommand extends Command
{
    private Filesystem $filesystem;

    public function __construct() {
        parent::__construct();
        $this->filesystem = new Filesystem();
    }

    protected function configure(): void {
        $this
            ->setName('examples:aggregate')
            ->setDescription('Aggregate package examples into centralized directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $targetDir = BasePath::get('examples');
        $packageDirs = [
            'instructor' => BasePath::get('packages/instructor/examples'),
            'polyglot' => BasePath::get('packages/polyglot/examples'),
        ];

        // Clear target directory
        if ($this->filesystem->exists($targetDir)) {
            $this->filesystem->remove($targetDir);
        }
        $this->filesystem->mkdir($targetDir);

        // Copy examples from packages
        foreach ($packageDirs as $package => $sourceDir) {
            if (!$this->filesystem->exists($sourceDir)) {
                continue;
            }

            $output->writeln("Aggregating examples from {$package}...");

            // Copy all example directories
            $this->copyExamples($sourceDir, $targetDir, $output);
        }

        $output->writeln('Example aggregation completed.');
        return Command::SUCCESS;
    }

    private function copyExamples(string $sourceDir, string $targetDir, OutputInterface $output): void {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $targetDir . '/' . $iterator->getSubPathName();

            if ($item->isDir()) {
                $this->filesystem->mkdir($target);
            } else {
                $this->filesystem->copy($item->getRealPath(), $target);
                $output->writeln("  Copied: {$iterator->getSubPathName()}");
            }
        }
    }
}
```

#### 1.5 Register New Command

**File:** `packages/doctor/src/Docs.php`

**Add to registerCommands method:**

```php
private function registerCommands(): void
{
    // ... existing commands

    $this->addCommands([
        // ... existing commands
        new AggregateExamplesCommand(),
    ]);
}
```

#### 1.6 Update Composer Scripts

**File:** `composer.json`

**Modify scripts section:**

```json
{
    "scripts": {
        "docs": [
            "@php ./bin/instructor-docs examples:aggregate",
            "@php ./bin/instructor-docs gen:mintlify"
        ],
        "docs:aggregate": "@php ./bin/instructor-docs examples:aggregate",
        "docs:examples": "@php ./bin/instructor-docs gen:examples",
        "docs:packages": "@php ./bin/instructor-docs gen:packages"
    }
}
```

### Phase 2: Content Migration

#### 2.1 Migration Script

**File:** `scripts/migrate-examples.php`

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Cognesy\Config\BasePath;
use Symfony\Component\Filesystem\Filesystem;

class ExampleMigrator
{
    private Filesystem $filesystem;
    private array $packageMappings = [
        // Instructor examples (A01-A05)
        'A01_Basics' => 'packages/instructor/examples/basics',
        'A02_Advanced' => 'packages/instructor/examples/advanced',
        'A03_Troubleshooting' => 'packages/instructor/examples/troubleshooting',
        'A04_APISupport' => 'packages/instructor/examples/api_support',
        'A05_Extras' => 'packages/instructor/examples/extras',

        // Polyglot examples (B01-B05)
        'B01_LLM' => 'packages/polyglot/examples/llm_basics',
        'B02_LLMAdvanced' => 'packages/polyglot/examples/llm_advanced',
        'B03_LLMTroubleshooting' => 'packages/polyglot/examples/llm_troubleshooting',
        'B04_LLMApiSupport' => 'packages/polyglot/examples/llm_api_support',
        'B05_LLMExtras' => 'packages/polyglot/examples/llm_extras',

        // Prompting examples (C01-C07) -> Keep with Instructor for now
        'C01_ZeroShot' => 'packages/instructor/examples/prompting/zero_shot',
        'C02_FewShot' => 'packages/instructor/examples/prompting/few_shot',
        'C03_ThoughtGen' => 'packages/instructor/examples/prompting/thought_gen',
        'C04_Ensembling' => 'packages/instructor/examples/prompting/ensembling',
        'C05_SelfCriticism' => 'packages/instructor/examples/prompting/self_criticism',
        'C06_Decomposition' => 'packages/instructor/examples/prompting/decomposition',
        'C07_Misc' => 'packages/instructor/examples/prompting/misc',
    ];

    public function __construct() {
        $this->filesystem = new Filesystem();
    }

    public function migrate(bool $dryRun = true): void {
        $sourceDir = BasePath::get('examples');

        foreach ($this->packageMappings as $sourceGroup => $targetPath) {
            $sourcePath = $sourceDir . '/' . $sourceGroup;
            $targetDir = BasePath::get($targetPath);

            if (!is_dir($sourcePath)) {
                echo "Source directory not found: $sourcePath\n";
                continue;
            }

            echo "Migrating $sourceGroup -> $targetPath\n";

            if (!$dryRun) {
                $this->filesystem->mkdir(dirname($targetDir));
                $this->filesystem->mirror($sourcePath, $targetDir);
                echo "  Copied to: $targetDir\n";
            } else {
                echo "  [DRY RUN] Would copy to: $targetDir\n";
            }
        }
    }

    public function validateMigration(): bool {
        $success = true;

        foreach ($this->packageMappings as $sourceGroup => $targetPath) {
            $targetDir = BasePath::get($targetPath);

            if (!is_dir($targetDir)) {
                echo "ERROR: Target directory missing: $targetDir\n";
                $success = false;
                continue;
            }

            // Check for run.php files in each example
            $examples = glob($targetDir . '/*/run.php');
            if (empty($examples)) {
                echo "WARNING: No examples found in: $targetDir\n";
            } else {
                echo "OK: Found " . count($examples) . " examples in $targetPath\n";
            }
        }

        return $success;
    }
}

// CLI interface
$action = $argv[1] ?? 'help';
$migrator = new ExampleMigrator();

switch ($action) {
    case 'migrate':
        $dryRun = ($argv[2] ?? 'dry-run') === 'dry-run';
        echo $dryRun ? "DRY RUN MODE\n" : "LIVE MIGRATION MODE\n";
        $migrator->migrate($dryRun);
        break;

    case 'validate':
        $success = $migrator->validateMigration();
        exit($success ? 0 : 1);

    case 'help':
    default:
        echo "Usage: php scripts/migrate-examples.php [migrate|validate|help] [dry-run|live]\n";
        echo "  migrate dry-run  - Show what would be migrated (default)\n";
        echo "  migrate live     - Actually perform migration\n";
        echo "  validate         - Check migration results\n";
        echo "  help             - Show this help\n";
        break;
}
```

#### 2.2 Migration Process

1. **Test current system:**
   ```bash
   composer docs
   # Verify documentation builds correctly
   ```

2. **Run migration in dry-run mode:**
   ```bash
   php scripts/migrate-examples.php migrate dry-run
   ```

3. **Perform actual migration:**
   ```bash
   php scripts/migrate-examples.php migrate live
   ```

4. **Validate migration:**
   ```bash
   php scripts/migrate-examples.php validate
   ```

5. **Test with new structure:**
   ```bash
   composer docs:aggregate
   composer docs
   # Verify documentation still builds correctly
   ```

### Phase 3: Example Mapping Updates

#### 3.1 Update Example Class

**File:** `packages/hub/src/Data/Example.php`

**Modify loadExample method:**

```php
private static function loadExample(string $baseDir, string $path, int $index = 0): self {
    // Enhanced path handling for package-based examples
    [$group, $name] = explode('/', $path, 2);

    // Determine package from path or group
    $package = self::detectPackage($baseDir, $group);

    $info = ExampleInfo::fromFile($baseDir . $path . '/run.php', $name);

    // Enhanced mapping with package detection
    $mapping = self::getPackageMapping($package, $group);

    $tab = $mapping['tab'] ?? '';
    return new Example(
        index: $index,
        tab: $tab,
        group: $mapping['name'] ?? '',
        groupTitle: $mapping['title'] ?? '',
        name: $name,
        hasTitle: $info->hasTitle(),
        title: $info->title,
        docName: $info->docName,
        content: $info->content,
        directory: $baseDir . $path,
        relativePath: './' . $tab . '/' . $path . '/run.php',
        runPath: $baseDir . $path . '/run.php',
    );
}

private static function detectPackage(string $baseDir, string $group): string {
    // Detect package from base directory path
    if (str_contains($baseDir, 'packages/instructor/')) {
        return 'instructor';
    }
    if (str_contains($baseDir, 'packages/polyglot/')) {
        return 'polyglot';
    }

    // Fallback: detect from group prefix
    if (str_starts_with($group, 'A0')) return 'instructor';
    if (str_starts_with($group, 'B0')) return 'polyglot';
    if (str_starts_with($group, 'C0')) return 'instructor'; // Prompting with Instructor

    return 'general';
}

private static function getPackageMapping(string $package, string $group): array {
    $mappings = [
        'instructor' => [
            'A01_Basics' => ['tab' => 'instructor', 'name' => 'basics', 'title' => 'Cookbook \ Instructor \ Basics'],
            'A02_Advanced' => ['tab' => 'instructor', 'name' => 'advanced', 'title' => 'Cookbook \ Instructor \ Advanced'],
            // ... rest of instructor mappings
            'basics' => ['tab' => 'instructor', 'name' => 'basics', 'title' => 'Cookbook \ Instructor \ Basics'],
            'advanced' => ['tab' => 'instructor', 'name' => 'advanced', 'title' => 'Cookbook \ Instructor \ Advanced'],
            // ... new package-based names
        ],
        'polyglot' => [
            'B01_LLM' => ['tab' => 'polyglot', 'name' => 'llm_basics', 'title' => 'Cookbook \ Polyglot \ LLM Basics'],
            // ... rest of polyglot mappings
            'llm_basics' => ['tab' => 'polyglot', 'name' => 'llm_basics', 'title' => 'Cookbook \ Polyglot \ LLM Basics'],
            // ... new package-based names
        ],
    ];

    return $mappings[$package][$group] ?? ['tab' => $package, 'name' => $group, 'title' => ucfirst($group)];
}
```

### Phase 4: Testing and Validation

#### 4.1 Test Suite

Create test script to validate the migration:

**File:** `scripts/test-migration.php`

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Config\BasePath;

class MigrationTester
{
    public function testExampleDiscovery(): bool {
        echo "Testing example discovery...\n";

        $directories = [
            BasePath::get('examples'),
            BasePath::get('packages/instructor/examples'),
            BasePath::get('packages/polyglot/examples'),
        ];

        $repository = new ExampleRepository($directories);
        $groups = $repository->getExampleGroups();

        echo "Found " . count($groups) . " example groups\n";

        $totalExamples = 0;
        foreach ($groups as $group) {
            echo "  Group: {$group->name} ({$group->title}) - " . count($group->examples) . " examples\n";
            $totalExamples += count($group->examples);
        }

        echo "Total examples: $totalExamples\n";
        return $totalExamples > 0;
    }

    public function testDocumentationGeneration(): bool {
        echo "Testing documentation generation...\n";

        // Test command execution
        $output = [];
        $returnCode = 0;
        exec('composer docs 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            echo "ERROR: Documentation generation failed\n";
            echo implode("\n", $output);
            return false;
        }

        // Check output files exist
        $expectedFiles = [
            'docs-build/cookbook/instructor',
            'docs-build/cookbook/polyglot',
            'docs-build/mint.json',
        ];

        foreach ($expectedFiles as $file) {
            $path = BasePath::get($file);
            if (!file_exists($path)) {
                echo "ERROR: Expected file missing: $path\n";
                return false;
            }
        }

        echo "Documentation generation successful\n";
        return true;
    }

    public function testNavigationGeneration(): bool {
        echo "Testing navigation generation...\n";

        $mintJsonPath = BasePath::get('docs-build/mint.json');
        if (!file_exists($mintJsonPath)) {
            echo "ERROR: mint.json not found\n";
            return false;
        }

        $mintJson = json_decode(file_get_contents($mintJsonPath), true);
        if (!$mintJson) {
            echo "ERROR: Invalid mint.json\n";
            return false;
        }

        // Check for cookbook navigation
        $hasInstructor = false;
        $hasPolyglot = false;

        foreach ($mintJson['navigation'] as $section) {
            if (isset($section['group']) && str_contains($section['group'], 'Instructor')) {
                $hasInstructor = true;
            }
            if (isset($section['group']) && str_contains($section['group'], 'Polyglot')) {
                $hasPolyglot = true;
            }
        }

        if (!$hasInstructor || !$hasPolyglot) {
            echo "ERROR: Missing expected navigation sections\n";
            return false;
        }

        echo "Navigation generation successful\n";
        return true;
    }
}

$tester = new MigrationTester();
$tests = [
    'Example Discovery' => [$tester, 'testExampleDiscovery'],
    'Documentation Generation' => [$tester, 'testDocumentationGeneration'],
    'Navigation Generation' => [$tester, 'testNavigationGeneration'],
];

$results = [];
foreach ($tests as $testName => $callback) {
    echo "\n" . str_repeat('=', 50) . "\n";
    echo "Running test: $testName\n";
    echo str_repeat('=', 50) . "\n";

    $results[$testName] = call_user_func($callback);
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "TEST RESULTS\n";
echo str_repeat('=', 50) . "\n";

$passed = 0;
$total = count($results);

foreach ($results as $testName => $result) {
    $status = $result ? 'PASS' : 'FAIL';
    echo "$testName: $status\n";
    if ($result) $passed++;
}

echo "\nPassed: $passed/$total\n";
exit($passed === $total ? 0 : 1);
```

#### 4.2 Validation Steps

1. **Run test suite before migration:**
   ```bash
   php scripts/test-migration.php
   ```

2. **Perform migration with testing at each step:**
   ```bash
   # 1. Test current state
   php scripts/test-migration.php

   # 2. Migrate
   php scripts/migrate-examples.php migrate live

   # 3. Test aggregation
   composer docs:aggregate
   php scripts/test-migration.php

   # 4. Test full documentation build
   composer docs
   php scripts/test-migration.php
   ```

3. **Manual verification:**
   - Check generated documentation in browser
   - Verify all example links work
   - Test both Mintlify and MkDocs outputs
   - Validate search functionality if applicable

## Rollback Plan

If migration causes issues:

1. **Immediate rollback:**
   ```bash
   git checkout examples/  # Restore original examples
   composer docs           # Rebuild with original structure
   ```

2. **Partial rollback:**
   ```bash
   # Keep package examples but restore centralized build
   git checkout packages/doctor/src/Docs.php
   composer docs
   ```

3. **Investigation mode:**
   ```bash
   # Compare outputs
   diff -r docs-build-before/ docs-build-after/
   ```

## Maintenance

### Adding New Examples

After migration, add examples to appropriate package:

```bash
# For Instructor examples
mkdir packages/instructor/examples/new-category/my-example
echo '<?php // example code' > packages/instructor/examples/new-category/my-example/run.php

# For Polyglot examples
mkdir packages/polyglot/examples/new-category/my-example
echo '<?php // example code' > packages/polyglot/examples/new-category/my-example/run.php

# Rebuild aggregated examples
composer docs:aggregate
```

### Updating Build Process

When adding new packages with examples:

1. Update `ExampleRepository` constructor in `Docs.php`
2. Update migration mappings if needed
3. Update aggregation command to include new packages
4. Test full documentation build

This implementation guide provides the technical foundation for successfully migrating to a package-specific examples structure while maintaining documentation build system compatibility.