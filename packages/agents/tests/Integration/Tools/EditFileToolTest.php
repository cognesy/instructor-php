<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Integration\Tools;

use Cognesy\Agents\Capability\File\EditFileTool;

describe('EditFileTool', function () {

    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/edit_file_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function () {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*') ?: []);
            rmdir($this->tempDir);
        }
    });

    it('has correct name and description', function () {
        $tool = new EditFileTool(baseDir: $this->tempDir);

        expect($tool->name())->toBe('edit_file');
        expect($tool->description())->toContain('Edit a file by replacing');
    });

    it('replaces unique string in file', function () {
        $path = $this->tempDir . '/test.txt';
        file_put_contents($path, "Hello World");

        $tool = new EditFileTool(baseDir: $this->tempDir);
        $result = $tool($path, 'World', 'Universe');

        expect($result)->toContain('Successfully replaced 1 occurrence');
        expect(file_get_contents($path))->toBe('Hello Universe');
    });

    it('returns error when string not found', function () {
        $path = $this->tempDir . '/test.txt';
        file_put_contents($path, "Hello World");

        $tool = new EditFileTool(baseDir: $this->tempDir);
        $result = $tool($path, 'NotFound', 'Replacement');

        expect($result)->toContain('Error:');
        expect($result)->toContain('not found');
    });

    it('returns error when multiple occurrences without replace_all', function () {
        $path = $this->tempDir . '/test.txt';
        file_put_contents($path, "Hello Hello Hello");

        $tool = new EditFileTool(baseDir: $this->tempDir);
        $result = $tool($path, 'Hello', 'Hi');

        expect($result)->toContain('Error:');
        expect($result)->toContain('3 times');
        expect($result)->toContain('replace_all=true');
    });

    it('replaces all occurrences with replace_all flag', function () {
        $path = $this->tempDir . '/test.txt';
        file_put_contents($path, "Hello Hello Hello");

        $tool = new EditFileTool(baseDir: $this->tempDir);
        $result = $tool($path, 'Hello', 'Hi', replace_all: true);

        expect($result)->toContain('Successfully replaced 3 occurrence');
        expect(file_get_contents($path))->toBe('Hi Hi Hi');
    });

    it('returns error when old and new string are identical', function () {
        $path = $this->tempDir . '/test.txt';
        file_put_contents($path, "Hello World");

        $tool = new EditFileTool(baseDir: $this->tempDir);
        $result = $tool($path, 'World', 'World');

        expect($result)->toContain('Error:');
        expect($result)->toContain('identical');
    });

    it('returns error when old_string is empty', function () {
        $path = $this->tempDir . '/test.txt';
        file_put_contents($path, "Hello World");

        $tool = new EditFileTool(baseDir: $this->tempDir);
        $result = $tool($path, '', 'replacement');

        expect($result)->toContain('Error:');
        expect($result)->toContain('cannot be empty');
    });

    it('returns error for non-existent file', function () {
        $tool = new EditFileTool(baseDir: $this->tempDir);
        $result = $tool($this->tempDir . '/nonexistent.txt', 'old', 'new');

        expect($result)->toContain('Error:');
        expect($result)->toContain('Cannot read file');
    });

    it('preserves content around replacement', function () {
        $path = $this->tempDir . '/test.txt';
        file_put_contents($path, "prefix MARKER suffix");

        $tool = new EditFileTool(baseDir: $this->tempDir);
        $tool($path, 'MARKER', 'REPLACED');

        expect(file_get_contents($path))->toBe('prefix REPLACED suffix');
    });

    it('handles multiline content', function () {
        $path = $this->tempDir . '/test.txt';
        $content = "line1\nOLD_LINE\nline3";
        file_put_contents($path, $content);

        $tool = new EditFileTool(baseDir: $this->tempDir);
        $tool($path, 'OLD_LINE', 'NEW_LINE');

        expect(file_get_contents($path))->toBe("line1\nNEW_LINE\nline3");
    });

    it('streams edits without truncating a file larger than the sandbox output cap', function () {
        $path = $this->tempDir . '/large.txt';
        $prefix = str_repeat('a', 2 * 1024 * 1024);
        $suffix = str_repeat('z', 2 * 1024 * 1024);
        file_put_contents($path, $prefix . 'UNIQUE_MARKER' . $suffix);

        $tool = new EditFileTool(baseDir: $this->tempDir);
        $result = $tool($path, 'UNIQUE_MARKER', 'REPLACED');

        expect($result)->toContain('streamed atomically');
        expect(filesize($path))->toBe(strlen($prefix . 'REPLACED' . $suffix));
        expect(file_get_contents($path))->toBe($prefix . 'REPLACED' . $suffix);
    });

    it('matches text that crosses an internal stream boundary', function () {
        $path = $this->tempDir . '/boundary.txt';
        $prefix = str_repeat('a', (64 * 1024) - 3);
        file_put_contents($path, $prefix . 'BOUNDARY' . 'suffix');

        $tool = new EditFileTool(baseDir: $this->tempDir);
        $result = $tool($path, 'BOUNDARY', 'REPLACED');

        expect($result)->toContain('Successfully replaced 1 occurrence');
        expect(file_get_contents($path))->toBe($prefix . 'REPLACEDsuffix');
    });

    it('leaves a large source unchanged when uniqueness validation fails', function () {
        $path = $this->tempDir . '/multiple-large.txt';
        $content = 'MARKER' . str_repeat('x', 2 * 1024 * 1024) . 'MARKER';
        file_put_contents($path, $content);

        $tool = new EditFileTool(baseDir: $this->tempDir);
        $result = $tool($path, 'MARKER', 'REPLACED');

        expect($result)->toContain('found 2 times');
        expect($result)->toContain('The file was not changed');
        expect(file_get_contents($path))->toBe($content);
    });

    it('creates tool from directory', function () {
        $tool = EditFileTool::inDirectory($this->tempDir);

        expect($tool)->toBeInstanceOf(EditFileTool::class);
    });

    it('generates valid tool schema', function () {
        $tool = new EditFileTool(baseDir: $this->tempDir);
        $schema = $tool->toToolSchema();

        expect($schema->name())->toBe('edit_file');
        expect($schema->parameters())->toBeArray();
    });
});
