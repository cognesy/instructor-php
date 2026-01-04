<?php declare(strict_types=1);

use Cognesy\Addons\Agent\Tools\ReadFileTool;

describe('ReadFileTool', function () {

    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/read_file_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function () {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*') ?: []);
            rmdir($this->tempDir);
        }
    });

    it('has correct name and description', function () {
        $tool = new ReadFileTool(baseDir: $this->tempDir);

        expect($tool->name())->toBe('read_file');
        expect($tool->description())->toContain('Read the contents of a file');
    });

    it('reads file with line numbers', function () {
        $content = "line one\nline two\nline three";
        file_put_contents($this->tempDir . '/test.txt', $content);

        $tool = new ReadFileTool(baseDir: $this->tempDir);
        $result = $tool($this->tempDir . '/test.txt');

        expect($result)->toContain('1');
        expect($result)->toContain('line one');
        expect($result)->toContain('2');
        expect($result)->toContain('line two');
        expect($result)->toContain('3');
        expect($result)->toContain('line three');
    });

    it('reads file with offset', function () {
        $content = "line1\nline2\nline3\nline4\nline5";
        file_put_contents($this->tempDir . '/test.txt', $content);

        $tool = new ReadFileTool(baseDir: $this->tempDir);
        $result = $tool($this->tempDir . '/test.txt', offset: 2);

        expect($result)->not->toContain('line1');
        expect($result)->not->toContain('line2');
        expect($result)->toContain('line3');
        expect($result)->toContain('3');
    });

    it('reads file with limit', function () {
        $content = "line1\nline2\nline3\nline4\nline5";
        file_put_contents($this->tempDir . '/test.txt', $content);

        $tool = new ReadFileTool(baseDir: $this->tempDir);
        $result = $tool($this->tempDir . '/test.txt', limit: 2);

        expect($result)->toContain('line1');
        expect($result)->toContain('line2');
        expect($result)->not->toContain('line3');
    });

    it('reads file with offset and limit', function () {
        $content = "line1\nline2\nline3\nline4\nline5";
        file_put_contents($this->tempDir . '/test.txt', $content);

        $tool = new ReadFileTool(baseDir: $this->tempDir);
        $result = $tool($this->tempDir . '/test.txt', offset: 1, limit: 2);

        expect($result)->not->toContain('line1');
        expect($result)->toContain('line2');
        expect($result)->toContain('line3');
        expect($result)->not->toContain('line4');
    });

    it('returns error for non-existent file', function () {
        $tool = new ReadFileTool(baseDir: $this->tempDir);

        $result = $tool($this->tempDir . '/nonexistent.txt');

        expect($result)->toContain('Error:');
    });

    it('returns empty file message', function () {
        file_put_contents($this->tempDir . '/empty.txt', '');

        $tool = new ReadFileTool(baseDir: $this->tempDir);
        $result = $tool($this->tempDir . '/empty.txt');

        expect($result)->toBe('(empty file)');
    });

    it('creates tool from directory', function () {
        $tool = ReadFileTool::inDirectory($this->tempDir);

        expect($tool)->toBeInstanceOf(ReadFileTool::class);
    });

    it('generates valid tool schema', function () {
        $tool = new ReadFileTool(baseDir: $this->tempDir);
        $schema = $tool->toToolSchema();

        expect($schema['type'])->toBe('function');
        expect($schema['function']['name'])->toBe('read_file');
        expect($schema['function']['parameters'])->toBeArray();
    });
});
