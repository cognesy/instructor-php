<?php declare(strict_types=1);

namespace Cognesy\Doctor\Tests\Unit\Docgen;

use Cognesy\Doctor\Docgen\LlmsDocsGenerator;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/llms-docs-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);

    $this->generator = new LlmsDocsGenerator(
        projectName: 'Test Project',
        projectDescription: 'A test project for unit testing.',
    );
});

afterEach(function () {
    // Clean up temp directory
    if (is_dir($this->tempDir)) {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($this->tempDir);
    }
});

describe('LlmsDocsGenerator', function () {

    describe('generateIndex', function () {

        it('generates valid llms.txt with header', function () {
            $navigation = [
                ['Main' => [
                    ['Overview' => 'index.md'],
                    ['Getting Started' => 'getting-started.md'],
                ]],
            ];

            $outputPath = $this->tempDir . '/llms.txt';
            $result = $this->generator->generateIndex($navigation, $outputPath);

            expect($result->isSuccess())->toBeTrue();
            expect(file_exists($outputPath))->toBeTrue();

            $content = file_get_contents($outputPath);
            expect($content)->toContain('# Test Project');
            expect($content)->toContain('> A test project for unit testing.');
        });

        it('renders flat navigation as markdown links', function () {
            $navigation = [
                ['Main' => [
                    ['Overview' => 'index.md'],
                    ['Features' => 'features.md'],
                ]],
            ];

            $outputPath = $this->tempDir . '/llms.txt';
            $this->generator->generateIndex($navigation, $outputPath);

            $content = file_get_contents($outputPath);
            expect($content)->toContain('## Main');
            expect($content)->toContain('- [Overview](index.md)');
            expect($content)->toContain('- [Features](features.md)');
        });

        it('renders nested navigation with proper hierarchy', function () {
            $navigation = [
                ['Packages' => [
                    ['Overview' => 'packages/index.md'],
                    ['Instructor' => [
                        ['Introduction' => 'packages/instructor/intro.md'],
                        ['Setup' => 'packages/instructor/setup.md'],
                    ]],
                ]],
            ];

            $outputPath = $this->tempDir . '/llms.txt';
            $this->generator->generateIndex($navigation, $outputPath);

            $content = file_get_contents($outputPath);
            expect($content)->toContain('## Packages');
            expect($content)->toContain('- [Overview](packages/index.md)');
            expect($content)->toContain('### Instructor');
            expect($content)->toContain('- [Introduction](packages/instructor/intro.md)');
        });

        it('handles multiple sections', function () {
            $navigation = [
                ['Main' => [
                    ['Index' => 'index.md'],
                ]],
                ['Packages' => [
                    ['Overview' => 'packages/index.md'],
                ]],
                ['Cookbook' => [
                    ['Examples' => 'cookbook/examples.md'],
                ]],
            ];

            $outputPath = $this->tempDir . '/llms.txt';
            $this->generator->generateIndex($navigation, $outputPath);

            $content = file_get_contents($outputPath);
            expect($content)->toContain('## Main');
            expect($content)->toContain('## Packages');
            expect($content)->toContain('## Cookbook');
        });

        it('reports file size in success message', function () {
            $navigation = [
                ['Main' => [
                    ['Index' => 'index.md'],
                ]],
            ];

            $outputPath = $this->tempDir . '/llms.txt';
            $result = $this->generator->generateIndex($navigation, $outputPath);

            expect($result->message)->toContain('Generated llms.txt');
            expect($result->message)->toMatch('/\d+(\.\d+)?\s*(B|KB|MB)/');
        });

    });

    describe('generateFull', function () {

        beforeEach(function () {
            // Create sample markdown files
            mkdir($this->tempDir . '/source', 0755, true);
            mkdir($this->tempDir . '/source/packages', 0755, true);

            file_put_contents($this->tempDir . '/source/index.md', <<<'MD'
---
title: Home
---

# Welcome

This is the home page.
MD);

            file_put_contents($this->tempDir . '/source/features.md', <<<'MD'
# Features

- Feature 1
- Feature 2
MD);

            file_put_contents($this->tempDir . '/source/packages/intro.md', <<<'MD'
---
title: Packages
description: Package overview
---

# Packages Overview

All packages documentation.
MD);
        });

        it('concatenates files in navigation order', function () {
            $navigation = [
                ['Main' => [
                    ['Home' => 'index.md'],
                    ['Features' => 'features.md'],
                ]],
                ['Packages' => [
                    ['Intro' => 'packages/intro.md'],
                ]],
            ];

            $outputPath = $this->tempDir . '/llms-full.txt';
            $result = $this->generator->generateFull(
                $navigation,
                $this->tempDir . '/source',
                $outputPath
            );

            expect($result->isSuccess())->toBeTrue();
            expect($result->filesProcessed)->toBe(3);

            $content = file_get_contents($outputPath);

            // Check files appear in order
            $indexPos = strpos($content, 'FILE: index.md');
            $featuresPos = strpos($content, 'FILE: features.md');
            $packagesPos = strpos($content, 'FILE: packages/intro.md');

            expect($indexPos)->toBeLessThan($featuresPos);
            expect($featuresPos)->toBeLessThan($packagesPos);
        });

        it('strips YAML frontmatter', function () {
            $navigation = [
                ['Main' => [
                    ['Home' => 'index.md'],
                ]],
            ];

            $outputPath = $this->tempDir . '/llms-full.txt';
            $this->generator->generateFull(
                $navigation,
                $this->tempDir . '/source',
                $outputPath
            );

            $content = file_get_contents($outputPath);
            expect($content)->not->toContain('---');
            expect($content)->not->toContain('title: Home');
            expect($content)->toContain('# Welcome');
        });

        it('adds file separators', function () {
            $navigation = [
                ['Main' => [
                    ['Home' => 'index.md'],
                    ['Features' => 'features.md'],
                ]],
            ];

            $outputPath = $this->tempDir . '/llms-full.txt';
            $this->generator->generateFull(
                $navigation,
                $this->tempDir . '/source',
                $outputPath
            );

            $content = file_get_contents($outputPath);
            expect($content)->toContain('================================================================================');
            expect($content)->toContain('FILE: index.md');
            expect($content)->toContain('FILE: features.md');
        });

        it('excludes patterns', function () {
            // Add release notes file
            mkdir($this->tempDir . '/source/release-notes', 0755, true);
            file_put_contents($this->tempDir . '/source/release-notes/v1.0.md', '# Version 1.0');

            $navigation = [
                ['Main' => [
                    ['Home' => 'index.md'],
                ]],
                ['Release Notes' => [
                    ['v1.0' => 'release-notes/v1.0.md'],
                ]],
            ];

            $outputPath = $this->tempDir . '/llms-full.txt';
            $this->generator->generateFull(
                $navigation,
                $this->tempDir . '/source',
                $outputPath,
                excludePatterns: ['release-notes/']
            );

            $content = file_get_contents($outputPath);
            expect($content)->not->toContain('FILE: release-notes/v1.0.md');
            expect($content)->not->toContain('# Version 1.0');
        });

        it('includes token estimate in success message', function () {
            $navigation = [
                ['Main' => [
                    ['Home' => 'index.md'],
                ]],
            ];

            $outputPath = $this->tempDir . '/llms-full.txt';
            $result = $this->generator->generateFull(
                $navigation,
                $this->tempDir . '/source',
                $outputPath
            );

            expect($result->message)->toContain('tokens');
        });

        it('skips missing files gracefully', function () {
            $navigation = [
                ['Main' => [
                    ['Home' => 'index.md'],
                    ['Missing' => 'nonexistent.md'],
                    ['Features' => 'features.md'],
                ]],
            ];

            $outputPath = $this->tempDir . '/llms-full.txt';
            $result = $this->generator->generateFull(
                $navigation,
                $this->tempDir . '/source',
                $outputPath
            );

            expect($result->isSuccess())->toBeTrue();
            expect($result->filesProcessed)->toBe(2); // Only 2 files exist
        });

        it('includes header with project info', function () {
            $navigation = [
                ['Main' => [
                    ['Home' => 'index.md'],
                ]],
            ];

            $outputPath = $this->tempDir . '/llms-full.txt';
            $this->generator->generateFull(
                $navigation,
                $this->tempDir . '/source',
                $outputPath
            );

            $content = file_get_contents($outputPath);
            expect($content)->toContain('# Test Project');
            expect($content)->toContain('> A test project for unit testing.');
        });

    });

});
