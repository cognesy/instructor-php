<?php declare(strict_types=1);

use Cognesy\Addons\Agent\Tools\File\WriteFileTool;
use Tests\Addons\Support\TestHelpers;

require_once __DIR__ . '/../../../Support/TestHelpers.php';

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
        expect($tool->description())->toContain('Write content to a file');
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

    it('overwrites existing file', function () {
        $path = $this->tempDir . '/existing.txt';
        file_put_contents($path, 'old content');

        $tool = new WriteFileTool(baseDir: $this->tempDir);
        $result = $tool($path, 'new content');

        expect($result)->toContain('Successfully wrote');
        expect(file_get_contents($path))->toBe('new content');
    });

    it('creates parent directories', function () {
        $tool = new WriteFileTool(baseDir: $this->tempDir);
        $path = $this->tempDir . '/subdir/nested/file.txt';

        $result = $tool($path, 'content');

        expect($result)->toContain('Successfully wrote');
        expect(file_exists($path))->toBeTrue();
    });

    it('counts lines correctly', function () {
        $tool = new WriteFileTool(baseDir: $this->tempDir);
        $content = "line1\nline2\nline3";

        $result = $tool($this->tempDir . '/multiline.txt', $content);

        expect($result)->toContain('3 lines');
    });

    it('creates tool from directory', function () {
        $tool = WriteFileTool::inDirectory($this->tempDir);

        expect($tool)->toBeInstanceOf(WriteFileTool::class);
    });

    it('generates valid tool schema', function () {
        $tool = new WriteFileTool(baseDir: $this->tempDir);
        $schema = $tool->toToolSchema();

        expect($schema['type'])->toBe('function');
        expect($schema['function']['name'])->toBe('write_file');
        expect($schema['function']['parameters'])->toBeArray();
    });
});
