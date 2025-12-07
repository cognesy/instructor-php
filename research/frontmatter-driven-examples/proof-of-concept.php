#!/usr/bin/env php
<?php

/**
 * Proof of Concept: Frontmatter-Driven Examples
 *
 * This script demonstrates how the enhanced frontmatter approach would work
 * by simulating the enhanced ExampleInfo and Example classes without
 * modifying the actual codebase.
 */

require __DIR__ . '/../../vendor/autoload.php';

use Cognesy\Utils\Markdown\FrontMatter;

class EnhancedExampleInfo
{
    public function __construct(
        public string $title,
        public string $docName,
        public string $content,
        public ?string $tab = null,
        public ?string $group = null,
        public ?string $groupTitle = null,
        public int $weight = 100,
        public array $tags = [],
        public ?string $package = null,
    ) {}

    public static function fromFile(string $path, string $name): self {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$path}");
        }

        $document = FrontMatter::parse($content);
        $data = $document->data();

        return new self(
            title: $data['title'] ?? self::extractTitle($document->document()),
            docName: $data['docname'] ?? strtolower(str_replace(' ', '_', $name)),
            content: $document->document(),
            tab: $data['tab'] ?? null,
            group: $data['group'] ?? null,
            groupTitle: $data['groupTitle'] ?? $data['group_title'] ?? null,
            weight: $data['weight'] ?? 100,
            tags: $data['tags'] ?? [],
            package: $data['package'] ?? null,
        );
    }

    private static function extractTitle(string $content): string {
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (substr($line, 0, 2) === '# ') {
                return trim(substr($line, 2));
            }
        }
        return 'Untitled';
    }
}

class EnhancedExample
{
    public function __construct(
        public int $index = 0,
        public string $tab = '',
        public string $group = '',
        public string $groupTitle = '',
        public string $name = '',
        public string $title = '',
        public string $docName = '',
        public string $directory = '',
        public string $runPath = '',
        public string $package = '',
        public int $weight = 100,
        public array $tags = [],
    ) {}

    public static function fromFile(string $baseDir, string $relativePath, int $index = 0): self {
        $parts = explode('/', $relativePath);
        $name = end($parts);

        $info = EnhancedExampleInfo::fromFile($baseDir . $relativePath . '/run.php', $name);

        // Detect package from base directory
        $package = self::detectPackageFromPath($baseDir);

        // Use frontmatter with intelligent fallbacks
        $tab = $info->tab ?? self::detectTabFromPackageOrGroup($package, $parts[0] ?? '');
        $group = $info->group ?? self::detectGroupFromPath($parts[0] ?? '');
        $groupTitle = $info->groupTitle ?? self::generateGroupTitle($tab, $group);

        return new self(
            index: $index,
            tab: $tab,
            group: $group,
            groupTitle: $groupTitle,
            name: $name,
            title: $info->title,
            docName: $info->docName,
            directory: $baseDir . $relativePath,
            runPath: $baseDir . $relativePath . '/run.php',
            package: $package,
            weight: $info->weight,
            tags: $info->tags,
        );
    }

    private static function detectPackageFromPath(string $baseDir): string {
        if (preg_match('#packages/([^/]+)/#', $baseDir, $matches)) {
            return $matches[1];
        }
        return 'core';
    }

    private static function detectTabFromPackageOrGroup(string $package, string $group): string {
        // Package-based detection
        $tabFromPackage = match($package) {
            'instructor' => 'instructor',
            'polyglot' => 'polyglot',
            'http-client' => 'http',
            default => null
        };

        if ($tabFromPackage) {
            return $tabFromPackage;
        }

        // Legacy group-based detection
        if (str_starts_with($group, 'A0')) return 'instructor';
        if (str_starts_with($group, 'B0')) return 'polyglot';
        if (str_starts_with($group, 'C0')) return 'prompting';

        return 'cookbook';
    }

    private static function detectGroupFromPath(string $legacyGroup): string {
        $legacyMapping = [
            'A01_Basics' => 'basics',
            'A02_Advanced' => 'advanced',
            'B01_LLM' => 'llm_basics',
            'C01_ZeroShot' => 'zero_shot',
        ];

        return $legacyMapping[$legacyGroup] ?? strtolower($legacyGroup);
    }

    private static function generateGroupTitle(string $tab, string $group): string {
        $titleMapping = [
            'basics' => 'Cookbook \ Instructor \ Basics',
            'advanced' => 'Cookbook \ Instructor \ Advanced',
            'llm_basics' => 'Cookbook \ Polyglot \ LLM Basics',
            'zero_shot' => 'Cookbook \ Prompting \ Zero-Shot Prompting',
        ];

        if (isset($titleMapping[$group])) {
            return $titleMapping[$group];
        }

        $tabTitle = ucfirst($tab);
        $groupTitle = ucwords(str_replace('_', ' ', $group));
        return "Cookbook \ {$tabTitle} \ {$groupTitle}";
    }

    public function toNavigationPath(): string {
        return "/{$this->tab}/{$this->group}/{$this->docName}";
    }
}

class ProofOfConceptRunner
{
    private array $examples = [];

    public function scanExamples(): void {
        // Simulate scanning multiple directories
        $directories = [
            __DIR__ . '/../../examples/' => 'core',
            // Would also scan packages/*/examples/ in real implementation
        ];

        $index = 1;
        foreach ($directories as $baseDir => $packageHint) {
            if (!is_dir($baseDir)) continue;

            $examples = $this->findExamplesInDirectory($baseDir);
            foreach ($examples as $relativePath) {
                $this->examples[] = EnhancedExample::fromFile($baseDir, $relativePath, $index++);
            }
        }
    }

    private function findExamplesInDirectory(string $baseDir): array {
        $examples = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && $item->getFilename() === 'run.php') {
                $relativePath = substr(dirname($item->getPathname()), strlen($baseDir));
                $examples[] = ltrim($relativePath, '/');
            }
        }

        return $examples;
    }

    public function showExamplesByNavigation(): void {
        $navigation = [];

        foreach ($this->examples as $example) {
            $tab = $example->tab;
            $group = $example->group;

            if (!isset($navigation[$tab])) {
                $navigation[$tab] = [];
            }

            if (!isset($navigation[$tab][$group])) {
                $navigation[$tab][$group] = [
                    'title' => $example->groupTitle,
                    'examples' => []
                ];
            }

            $navigation[$tab][$group]['examples'][] = [
                'title' => $example->title,
                'docName' => $example->docName,
                'path' => $example->toNavigationPath(),
                'package' => $example->package,
                'weight' => $example->weight,
                'tags' => $example->tags,
                'frontmatter_driven' => !empty($example->tab) && $example->package !== 'core'
            ];
        }

        // Sort examples by weight within each group
        foreach ($navigation as $tab => &$groups) {
            foreach ($groups as $group => &$groupData) {
                usort($groupData['examples'], fn($a, $b) => $a['weight'] <=> $b['weight']);
            }
        }

        echo "📋 Frontmatter-Driven Navigation Structure:\n\n";

        foreach ($navigation as $tab => $groups) {
            echo "🏷️ Tab: " . ucfirst($tab) . "\n";
            foreach ($groups as $group => $groupData) {
                echo "  📂 {$groupData['title']}\n";
                foreach ($groupData['examples'] as $example) {
                    $indicator = $example['frontmatter_driven'] ? '🆕' : '📰';
                    $tags = empty($example['tags']) ? '' : ' [' . implode(', ', $example['tags']) . ']';
                    echo "    {$indicator} {$example['title']}{$tags}\n";
                    echo "       📍 {$example['path']} (package: {$example['package']})\n";
                }
                echo "\n";
            }
        }
    }

    public function demonstrateFrontmatterOverride(): void {
        echo "🧪 Testing Frontmatter Override:\n\n";

        // Create a test example file with enhanced frontmatter
        $testDir = __DIR__ . '/test-example';
        $testFile = $testDir . '/run.php';

        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }

        file_put_contents($testFile, <<<'PHP'
---
title: 'Frontmatter-Driven Example'
docname: 'frontmatter_driven'
tab: 'instructor'
group: 'demos'
groupTitle: 'Cookbook \ Instructor \ Demonstrations'
weight: 50
tags: ['demo', 'frontmatter', 'proof-of-concept']
package: 'instructor'
---

# Frontmatter-Driven Example

This example demonstrates how frontmatter can control navigation placement.

## Features

- Custom tab placement via `tab` field
- Custom group via `group` field
- Custom group title via `groupTitle` field
- Ordering via `weight` field
- Tags for categorization

The navigation system reads these values and places the example accordingly.

PHP);

        // Parse the test example
        $example = EnhancedExample::fromFile(__DIR__ . '/', 'test-example', 999);

        echo "Example created with frontmatter:\n";
        echo "  Title: {$example->title}\n";
        echo "  Tab: {$example->tab}\n";
        echo "  Group: {$example->group}\n";
        echo "  Group Title: {$example->groupTitle}\n";
        echo "  Weight: {$example->weight}\n";
        echo "  Tags: " . implode(', ', $example->tags) . "\n";
        echo "  Package: {$example->package}\n";
        echo "  Navigation Path: {$example->toNavigationPath()}\n\n";

        // Clean up
        unlink($testFile);
        rmdir($testDir);
    }

    public function showMigrationPlan(): void {
        echo "📋 Migration Plan Preview:\n\n";

        $currentGroups = [];
        foreach ($this->examples as $example) {
            $key = $example->tab . '/' . $example->group;
            if (!isset($currentGroups[$key])) {
                $currentGroups[$key] = [
                    'tab' => $example->tab,
                    'group' => $example->group,
                    'groupTitle' => $example->groupTitle,
                    'count' => 0,
                    'packageSuggestion' => $example->tab === 'instructor' ? 'instructor' :
                                         ($example->tab === 'polyglot' ? 'polyglot' : 'instructor')
                ];
            }
            $currentGroups[$key]['count']++;
        }

        foreach ($currentGroups as $data) {
            echo "📁 {$data['groupTitle']} ({$data['count']} examples)\n";
            echo "   → Suggested location: packages/{$data['packageSuggestion']}/examples/{$data['group']}/\n";
            echo "   → Required frontmatter:\n";
            echo "     tab: '{$data['tab']}'\n";
            echo "     group: '{$data['group']}'\n";
            echo "     groupTitle: '{$data['groupTitle']}'\n\n";
        }
    }
}

// Run the proof of concept
echo "🚀 Proof of Concept: Frontmatter-Driven Examples\n";
echo str_repeat('=', 60) . "\n\n";

$runner = new ProofOfConceptRunner();
$runner->scanExamples();

echo "Current examples found: " . count($runner->examples ?? []) . "\n\n";

$runner->showExamplesByNavigation();
$runner->demonstrateFrontmatterOverride();
$runner->showMigrationPlan();

echo "✅ Proof of concept complete!\n";
echo "This demonstrates how frontmatter can drive navigation placement\n";
echo "while maintaining backward compatibility with existing examples.\n";