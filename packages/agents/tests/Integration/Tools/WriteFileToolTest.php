<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Integration\Tools;

use Cognesy\Agents\Capability\File\WriteFileTool;
use Cognesy\Agents\Tests\Support\TestHelpers;


describe('WriteFileTool', function () {

    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/write_file_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function () {
        TestHelpers::recursiveDelete($this->tempDir);
    });

    it('has correct name and description', function () {
        $tool = new WriteFileTool(baseDir: $this->tempDir);

        expect($tool->name())->toBe('write_file');
        expect($tool->description())->toContain('Create a new file');
    });

    it('writes content to new file', function () {
        $tool = new WriteFileTool(baseDir: $this->tempDir);
        $path = $this->tempDir . '/new_file.txt';
        $content = "Hello, World!";

        $result = $tool($path, $content);

        expect($result)->toContain('Successfully wrote');
        expect($result)->toContain('13 bytes');
        expect(file_get_contents($path))->toBe($content);
    });

    it('refuses to overwrite an existing file with different content', function () {
        $path = $this->tempDir . '/existing.txt';
        file_put_contents($path, 'old content');

        $tool = new WriteFileTool(baseDir: $this->tempDir);
        $result = $tool($path, 'new content');

        expect($result)->toContain('Target already exists with different content');
        expect($result)->toContain('the existing file was not changed');
        expect(file_get_contents($path))->toBe('old content');
    });

    it('treats an identical retry as a successful no-op', function () {
        $path = $this->tempDir . '/existing.txt';
        file_put_contents($path, 'same content');

        $tool = new WriteFileTool(baseDir: $this->tempDir);
        $result = $tool($path, 'same content');

        expect($result)->toContain('No change:');
        expect(file_get_contents($path))->toBe('same content');
    });

    it('requires parent directories to be created explicitly', function () {
        $tool = new WriteFileTool(baseDir: $this->tempDir);
        $path = $this->tempDir . '/subdir/nested/file.txt';

        $result = $tool($path, 'content');

        expect($result)->toContain('Parent directory does not exist');
        expect(file_exists($path))->toBeFalse();
    });

    it('counts lines correctly', function () {
        $tool = new WriteFileTool(baseDir: $this->tempDir);
        $content = "line1\nline2\nline3";

        $result = $tool($this->tempDir . '/multiline.txt', $content);

        expect($result)->toContain('3 lines');
    });

    it('streams a large payload to a new file', function () {
        $tool = new WriteFileTool(baseDir: $this->tempDir);
        $content = str_repeat('large-content\n', 200_000);
        $path = $this->tempDir . '/large.txt';

        $result = $tool($path, $content);

        expect($result)->toContain('streamed atomically');
        expect(filesize($path))->toBe(strlen($content));
        expect(hash_file('sha256', $path))->toBe(hash('sha256', $content));
    });

    it('creates tool from directory', function () {
        $tool = WriteFileTool::inDirectory($this->tempDir);

        expect($tool)->toBeInstanceOf(WriteFileTool::class);
    });

    it('generates valid tool schema', function () {
        $tool = new WriteFileTool(baseDir: $this->tempDir);
        $schema = $tool->toToolSchema();

        expect($schema->name())->toBe('write_file');
        expect($schema->parameters())->toBeArray();
    });
});
