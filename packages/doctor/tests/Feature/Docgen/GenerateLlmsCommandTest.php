<?php declare(strict_types=1);

namespace Cognesy\Doctor\Tests\Feature\Docgen;

use Cognesy\Doctor\Docgen\Commands\GenerateLlmsCommand;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/llms-command-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);
    mkdir($this->tempDir . '/docs', 0755, true);

    // Create sample markdown files
    file_put_contents($this->tempDir . '/docs/index.md', <<<'MD'
---
title: Home
---

# Welcome

This is the home page.
MD);

    file_put_contents($this->tempDir . '/docs/features.md', <<<'MD'
# Features

- Feature 1
- Feature 2
MD);

    // Create mock example repository
    $this->examples = $this->createMock(ExampleRepository::class);
    $this->examples->method('getExampleGroups')->willReturn([]);

    // Create command
    $this->command = new GenerateLlmsCommand(
        $this->examples,
        $this->tempDir . '/docs',
    );

    // Create application and add command
    $this->application = new Application();
    $this->application->addCommand($this->command);
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

describe('GenerateLlmsCommand', function () {

    it('generates llms.txt file', function () {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--index-only' => true]);

        expect($commandTester->getStatusCode())->toBe(0);

        $llmsPath = $this->tempDir . '/docs/llms.txt';
        expect(file_exists($llmsPath))->toBeTrue();

        $content = file_get_contents($llmsPath);
        expect($content)->toContain('# Instructor for PHP');
    });

    it('generates llms-full.txt file', function () {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--full-only' => true]);

        expect($commandTester->getStatusCode())->toBe(0);

        $fullPath = $this->tempDir . '/docs/llms-full.txt';
        expect(file_exists($fullPath))->toBeTrue();

        $content = file_get_contents($fullPath);
        expect($content)->toContain('# Instructor for PHP');
        expect($content)->toContain('FILE:');
    });

    it('generates both files by default', function () {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        expect($commandTester->getStatusCode())->toBe(0);

        expect(file_exists($this->tempDir . '/docs/llms.txt'))->toBeTrue();
        expect(file_exists($this->tempDir . '/docs/llms-full.txt'))->toBeTrue();
    });

    it('returns success exit code', function () {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        expect($commandTester->getStatusCode())->toBe(0);
    });

});
