# Frontmatter-Driven Examples Reorganization

## Executive Summary

This document provides a pragmatic implementation plan for decentralizing examples from the centralized `./examples` directory to package-specific `packages/*/examples/` directories, using frontmatter to drive documentation navigation placement instead of hardcoded directory structures.

## Current State Analysis

### Hub Commands Available
- `composer hub list` - Lists all examples
- `composer hub run <number>` - Runs specific example
- `composer hub all` - Runs all examples
- `composer hub show <number>` - Shows example details

### Docs Commands Available
- `composer docs` - Generates both Mintlify and MkDocs documentation
- `bin/instructor-docs gen:examples` - Generate example documentation only
- `bin/instructor-docs gen:mintlify` - Generate Mintlify documentation
- `bin/instructor-docs gen:mkdocs` - Generate MkDocs documentation
- `bin/instructor-docs gen:packages` - Generate package documentation

### Current Example Structure
```
examples/
├── A01_Basics/          # → instructor/basics
├── A02_Advanced/        # → instructor/advanced
├── A03_Troubleshooting/ # → instructor/troubleshooting
├── A04_APISupport/      # → instructor/api_support
├── A05_Extras/          # → instructor/extras
├── B01_LLM/            # → polyglot/llm_basics
├── B02_LLMAdvanced/    # → polyglot/llm_advanced
├── B03_LLMTroubleshooting/ # → polyglot/llm_troubleshooting
├── B04_LLMApiSupport/  # → polyglot/llm_api_support
├── B05_LLMExtras/      # → polyglot/llm_extras
├── C01_ZeroShot/       # → prompting/zero_shot
├── C02_FewShot/        # → prompting/few_shot
├── C03_ThoughtGen/     # → prompting/thought_gen
├── C04_Ensembling/     # → prompting/ensembling
├── C05_SelfCriticism/  # → prompting/self_criticism
├── C06_Decomposition/  # → prompting/decomposition
└── C07_Misc/           # → prompting/misc
```

### Current Frontmatter
Examples already have YAML frontmatter:
```yaml
---
title: 'Basic use'
docname: 'basic_use'
path: ''              # Currently unused
---
```

## Target Architecture

### Enhanced Frontmatter Structure
```yaml
---
title: 'Basic use'
docname: 'basic_use'
tab: 'instructor'           # Target documentation tab
group: 'basics'            # Documentation group/category
groupTitle: 'Basics'       # Display title for the group
weight: 100                # Optional: ordering within group
tags: ['beginner', 'core'] # Optional: for filtering/search
---
```

### Target Directory Structure
```
packages/
├── instructor/examples/
│   ├── basics/
│   │   ├── Basic/run.php              # tab: instructor, group: basics
│   │   └── Validation/run.php         # tab: instructor, group: basics
│   ├── advanced/
│   │   ├── Streaming/run.php          # tab: instructor, group: advanced
│   │   └── Partials/run.php           # tab: instructor, group: advanced
│   └── troubleshooting/
│       └── Debugging/run.php          # tab: instructor, group: troubleshooting
├── polyglot/examples/
│   ├── llm_basics/
│   │   ├── LLM/run.php                # tab: polyglot, group: llm_basics
│   │   └── LLMJson/run.php            # tab: polyglot, group: llm_basics
│   └── llm_advanced/
│       └── Embeddings/run.php         # tab: polyglot, group: llm_advanced
└── [future-packages]/examples/
```

## Implementation Plan

### Phase 1: Infrastructure Enhancement (Immediate)

#### 1.1 Enhance ExampleInfo to Parse Extended Frontmatter

**File:** `packages/hub/src/Data/ExampleInfo.php`

```php
public function __construct(
    public string $title,
    public string $docName,
    public string $content,
    public ?string $tab = null,           // NEW
    public ?string $group = null,         // NEW
    public ?string $groupTitle = null,    // NEW
    public int $weight = 100,             // NEW
    public array $tags = [],              // NEW
) {}

public static function fromFile(string $path, string $name): ExampleInfo {
    [$content, $data] = self::yamlFrontMatter($path);

    return new ExampleInfo(
        title: $data['title'] ?? self::getTitle($content),
        docName: $data['docname'] ?? Str::snake($name),
        content: $content,
        tab: $data['tab'] ?? null,
        group: $data['group'] ?? null,
        groupTitle: $data['groupTitle'] ?? null,
        weight: $data['weight'] ?? 100,
        tags: $data['tags'] ?? [],
    );
}
```

#### 1.2 Enhance Example Class for Package Detection

**File:** `packages/hub/src/Data/Example.php`

```php
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
    public string $package = '',              // NEW: detected package
    public int $weight = 100,                 // NEW: for ordering
    public array $tags = [],                  // NEW: for filtering
) {}

private static function loadExample(string $baseDir, string $path, int $index = 0): self {
    $info = ExampleInfo::fromFile($baseDir . $path . '/run.php', $name);

    // Detect package from directory structure
    $package = self::detectPackageFromPath($baseDir);

    // Use frontmatter values or fall back to detection
    $tab = $info->tab ?? self::detectTabFromPackage($package);
    $group = $info->group ?? self::detectGroupFromPath($path);
    $groupTitle = $info->groupTitle ?? self::generateGroupTitle($tab, $group);

    return new Example(
        // ... existing fields ...
        tab: $tab,
        group: $group,
        groupTitle: $groupTitle,
        package: $package,
        weight: $info->weight,
        tags: $info->tags,
        // ... rest of fields ...
    );
}

private static function detectPackageFromPath(string $baseDir): string {
    if (preg_match('#packages/([^/]+)/#', $baseDir, $matches)) {
        return $matches[1];
    }
    return 'core'; // fallback
}

private static function detectTabFromPackage(string $package): string {
    return match($package) {
        'instructor' => 'instructor',
        'polyglot' => 'polyglot',
        'http-client' => 'http',
        default => 'cookbook'
    };
}

private static function detectGroupFromPath(string $path): string {
    // Extract group from directory structure
    // e.g., "basics/Basic" -> "basics"
    $parts = explode('/', $path);
    return count($parts) > 1 ? $parts[0] : 'general';
}
```

#### 1.3 Enhance ExampleRepository for Multi-Package Support

**File:** `packages/hub/src/Services/ExampleRepository.php`

```php
private array $packageDirectories = [];

public function __construct(array $packageDirectories = []) {
    $this->packageDirectories = empty($packageDirectories)
        ? $this->getDefaultDirectories()
        : $packageDirectories;
}

private function getDefaultDirectories(): array {
    $basePath = BasePath::get('');
    return [
        $basePath . 'examples',                          // Legacy central location
        $basePath . 'packages/instructor/examples',      // Package-specific
        $basePath . 'packages/polyglot/examples',        // Package-specific
        $basePath . 'packages/http-client/examples',     // Package-specific
    ];
}

private function getExampleDirectories(): array {
    $allDirectories = [];

    foreach ($this->packageDirectories as $packageDir) {
        if (!is_dir($packageDir)) continue;

        $directories = $this->scanPackageDirectory($packageDir);
        $allDirectories = array_merge($allDirectories, $directories);
    }

    return array_unique($allDirectories);
}

private function scanPackageDirectory(string $baseDir): array {
    $directories = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir() && file_exists($item->getPathname() . '/run.php')) {
            $relativePath = substr($item->getPathname(), strlen($baseDir) + 1);
            $directories[] = $relativePath;
        }
    }

    return $directories;
}
```

### Phase 2: Migration Strategy (Gradual)

#### 2.1 Create Package Examples Directories
```bash
mkdir -p packages/instructor/examples
mkdir -p packages/polyglot/examples
mkdir -p packages/http-client/examples
```

#### 2.2 Enhanced Migration Script

**File:** `scripts/migrate-examples-frontmatter.php`

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

class FrontmatterExampleMigrator {
    private array $packageMapping = [
        // Current directory -> target package/group
        'A01_Basics' => ['package' => 'instructor', 'group' => 'basics', 'tab' => 'instructor'],
        'A02_Advanced' => ['package' => 'instructor', 'group' => 'advanced', 'tab' => 'instructor'],
        'A03_Troubleshooting' => ['package' => 'instructor', 'group' => 'troubleshooting', 'tab' => 'instructor'],
        'A04_APISupport' => ['package' => 'instructor', 'group' => 'api_support', 'tab' => 'instructor'],
        'A05_Extras' => ['package' => 'instructor', 'group' => 'extras', 'tab' => 'instructor'],

        'B01_LLM' => ['package' => 'polyglot', 'group' => 'llm_basics', 'tab' => 'polyglot'],
        'B02_LLMAdvanced' => ['package' => 'polyglot', 'group' => 'llm_advanced', 'tab' => 'polyglot'],
        'B03_LLMTroubleshooting' => ['package' => 'polyglot', 'group' => 'llm_troubleshooting', 'tab' => 'polyglot'],
        'B04_LLMApiSupport' => ['package' => 'polyglot', 'group' => 'llm_api_support', 'tab' => 'polyglot'],
        'B05_LLMExtras' => ['package' => 'polyglot', 'group' => 'llm_extras', 'tab' => 'polyglot'],

        'C01_ZeroShot' => ['package' => 'instructor', 'group' => 'prompting_zero_shot', 'tab' => 'prompting'],
        'C02_FewShot' => ['package' => 'instructor', 'group' => 'prompting_few_shot', 'tab' => 'prompting'],
        'C03_ThoughtGen' => ['package' => 'instructor', 'group' => 'prompting_thought_gen', 'tab' => 'prompting'],
        'C04_Ensembling' => ['package' => 'instructor', 'group' => 'prompting_ensembling', 'tab' => 'prompting'],
        'C05_SelfCriticism' => ['package' => 'instructor', 'group' => 'prompting_self_criticism', 'tab' => 'prompting'],
        'C06_Decomposition' => ['package' => 'instructor', 'group' => 'prompting_decomposition', 'tab' => 'prompting'],
        'C07_Misc' => ['package' => 'instructor', 'group' => 'prompting_misc', 'tab' => 'prompting'],
    ];

    private array $groupTitles = [
        'basics' => 'Basics',
        'advanced' => 'Advanced',
        'troubleshooting' => 'Troubleshooting',
        'api_support' => 'API Support',
        'extras' => 'Extras',
        'llm_basics' => 'LLM Basics',
        'llm_advanced' => 'LLM Advanced',
        'llm_troubleshooting' => 'LLM Troubleshooting',
        'llm_api_support' => 'LLM API Support',
        'llm_extras' => 'LLM Extras',
        'prompting_zero_shot' => 'Zero-Shot Prompting',
        'prompting_few_shot' => 'Few-Shot Prompting',
        'prompting_thought_gen' => 'Thought Generation',
        'prompting_ensembling' => 'Ensembling',
        'prompting_self_criticism' => 'Self-Criticism',
        'prompting_decomposition' => 'Decomposition',
        'prompting_misc' => 'Miscellaneous',
    ];

    public function migrateWithFrontmatter(bool $dryRun = true): void {
        $sourceBase = BasePath::get('examples');

        foreach ($this->packageMapping as $sourceDir => $mapping) {
            $sourcePath = $sourceBase . '/' . $sourceDir;
            if (!is_dir($sourcePath)) continue;

            $targetBase = BasePath::get("packages/{$mapping['package']}/examples/{$mapping['group']}");

            echo "Migrating $sourceDir to {$mapping['package']}/{$mapping['group']}\n";

            $this->migrateExamplesInDirectory($sourcePath, $targetBase, $mapping, $dryRun);
        }
    }

    private function migrateExamplesInDirectory(string $sourceDir, string $targetDir, array $mapping, bool $dryRun): void {
        $examples = glob($sourceDir . '/*/run.php');

        foreach ($examples as $exampleFile) {
            $exampleName = basename(dirname($exampleFile));
            $targetPath = $targetDir . '/' . $exampleName;

            if (!$dryRun) {
                $filesystem = new Symfony\Component\Filesystem\Filesystem();
                $filesystem->mkdir(dirname($targetPath));
                $filesystem->mirror(dirname($exampleFile), $targetPath);
            }

            // Update frontmatter
            $this->updateFrontmatter($targetPath . '/run.php', $mapping, $dryRun);
        }
    }

    private function updateFrontmatter(string $filePath, array $mapping, bool $dryRun): void {
        if ($dryRun) {
            echo "  [DRY RUN] Would update frontmatter in: $filePath\n";
            return;
        }

        $content = file_get_contents($filePath);
        $document = FrontMatter::parse($content);
        $frontmatter = $document->data();

        // Add new frontmatter fields
        $frontmatter['tab'] = $mapping['tab'];
        $frontmatter['group'] = $mapping['group'];
        $frontmatter['groupTitle'] = $this->groupTitles[$mapping['group']] ?? $mapping['group'];

        // Rebuild file with updated frontmatter
        $newContent = "---\n" . yaml_emit($frontmatter) . "---\n" . $document->document();
        file_put_contents($filePath, $newContent);

        echo "  Updated frontmatter in: $filePath\n";
    }
}

// CLI Usage
$action = $argv[1] ?? 'help';
$migrator = new FrontmatterExampleMigrator();

switch ($action) {
    case 'migrate':
        $dryRun = ($argv[2] ?? 'dry-run') === 'dry-run';
        $migrator->migrateWithFrontmatter($dryRun);
        break;
    case 'help':
    default:
        echo "Usage: php scripts/migrate-examples-frontmatter.php [migrate] [dry-run|live]\n";
        break;
}
```

### Phase 3: Documentation System Updates

#### 3.1 Update Documentation Configuration

**File:** `packages/doctor/src/Docs.php`

```php
private function registerServices(): void {
    // Multi-package example repository
    $exampleDirectories = [
        BasePath::get('examples'),                     // Legacy support
        BasePath::get('packages/instructor/examples'), // Package-specific
        BasePath::get('packages/polyglot/examples'),   // Package-specific
        BasePath::get('packages/http-client/examples'), // Package-specific
    ];

    $this->examples = new ExampleRepository($exampleDirectories);

    // ... rest of existing code
}
```

#### 3.2 Enhanced Navigation Generation

The existing MintlifyDocumentation system already supports dynamic navigation generation. The enhanced Example class will automatically provide the correct tab/group structure from frontmatter.

### Phase 4: Transition and Testing

#### 4.1 Gradual Migration Process

1. **Start with one package:**
   ```bash
   # Create instructor examples directory
   mkdir -p packages/instructor/examples/basics

   # Migrate one example as test
   php scripts/migrate-examples-frontmatter.php migrate dry-run
   php scripts/migrate-examples-frontmatter.php migrate live
   ```

2. **Test documentation generation:**
   ```bash
   composer docs
   # Verify examples appear in correct navigation sections
   ```

3. **Migrate remaining packages systematically**

#### 4.2 Validation Tools

**File:** `scripts/validate-frontmatter-migration.php`

```php
#!/usr/bin/env php
<?php

class FrontmatterValidator {
    public function validateMigration(): array {
        $repository = new ExampleRepository();
        $groups = $repository->getExampleGroups();

        $issues = [];

        foreach ($groups as $group) {
            foreach ($group->examples as $example) {
                // Validate required frontmatter fields
                if (empty($example->tab)) {
                    $issues[] = "Missing 'tab' in {$example->runPath}";
                }
                if (empty($example->group)) {
                    $issues[] = "Missing 'group' in {$example->runPath}";
                }
                if (empty($example->groupTitle)) {
                    $issues[] = "Missing 'groupTitle' in {$example->runPath}";
                }
            }
        }

        return $issues;
    }

    public function generateNavigationPreview(): array {
        $repository = new ExampleRepository();
        $groups = $repository->getExampleGroups();

        $navigation = [];

        foreach ($groups as $group) {
            $tabName = $group->examples[0]->tab ?? 'unknown';
            $groupName = $group->examples[0]->group ?? 'unknown';
            $groupTitle = $group->examples[0]->groupTitle ?? $groupName;

            if (!isset($navigation[$tabName])) {
                $navigation[$tabName] = [];
            }

            $navigation[$tabName][$groupName] = [
                'title' => $groupTitle,
                'examples' => array_map(fn($ex) => $ex->title, $group->examples)
            ];
        }

        return $navigation;
    }
}
```

## Benefits of This Approach

### 1. **Organic Structure**
- No hardcoded directory mappings (A01_, B01_, etc.)
- Examples define their own placement via frontmatter
- Easy to add new packages and reorganize

### 2. **Backward Compatibility**
- Existing examples continue working during transition
- Central ./examples can remain as fallback
- Documentation generation unchanged

### 3. **Package Ownership**
- Examples live with their related code
- Clear ownership boundaries
- Easier maintenance and contributions

### 4. **Flexible Navigation**
- Frontmatter drives navigation structure
- Easy to reorganize without moving files
- Support for weights, tags, and filtering

### 5. **Progressive Enhancement**
- Can be implemented incrementally
- No big-bang changes required
- Rollback-friendly approach

## Migration Timeline

### Week 1: Infrastructure
- [ ] Implement enhanced ExampleInfo and Example classes
- [ ] Update ExampleRepository for multi-package support
- [ ] Create migration scripts
- [ ] Test with single example

### Week 2: Package Migration
- [ ] Migrate instructor examples (A01-A05)
- [ ] Test documentation generation
- [ ] Migrate polyglot examples (B01-B05)
- [ ] Update navigation verification

### Week 3: Prompting Examples
- [ ] Migrate prompting examples (C01-C07)
- [ ] Decide final placement (instructor vs separate package)
- [ ] Test full documentation build

### Week 4: Cleanup & Documentation
- [ ] Remove legacy hardcoded mappings
- [ ] Update contributor documentation
- [ ] Add validation tools
- [ ] Complete migration

## Quick Start Implementation

To implement this immediately:

1. **Enhance frontmatter parsing:**
   ```bash
   # Update ExampleInfo.php to parse new fields
   ```

2. **Update one example as proof of concept:**
   ```yaml
   ---
   title: 'Basic use'
   docname: 'basic_use'
   tab: 'instructor'
   group: 'basics'
   groupTitle: 'Basics'
   weight: 100
   ---
   ```

3. **Test documentation generation:**
   ```bash
   composer docs
   ```

4. **Migrate package-by-package**

This approach provides a pragmatic path to achieve the desired organic, frontmatter-driven example organization while maintaining full backward compatibility and allowing for gradual migration.