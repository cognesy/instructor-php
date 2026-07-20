<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Integration\Tools;

use Cognesy\Agents\Capability\File\ReadFileTool;

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

    it('clamps a non-positive limit to one line', function () {
        file_put_contents($this->tempDir . '/test.txt', "line1\nline2");

        $tool = new ReadFileTool(baseDir: $this->tempDir);
        $result = $tool($this->tempDir . '/test.txt', limit: 0);

        expect($result)->toContain('line1');
        expect($result)->not->toContain('line2');
        expect($result)->toContain('Use offset=1 to continue');
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

    it('limits default reads to 200 lines and provides a continuation offset', function () {
        $lines = array_map(
            static fn(int $line): string => "line{$line}",
            range(1, 250),
        );
        file_put_contents($this->tempDir . '/large.txt', implode("\n", $lines));

        $tool = new ReadFileTool(baseDir: $this->tempDir);
        $result = $tool($this->tempDir . '/large.txt');

        expect($result)->toContain('line200');
        expect($result)->not->toContain('line201');
        expect($result)->toContain('Use offset=200 to continue');
    });

    it('allows an explicit line and byte window larger than the defaults', function () {
        $lines = array_map(
            static fn(int $line): string => "line{$line}",
            range(1, 500),
        );
        file_put_contents($this->tempDir . '/explicit-window.txt', implode("\n", $lines));

        $tool = new ReadFileTool(baseDir: $this->tempDir);
        $result = $tool(
            $this->tempDir . '/explicit-window.txt',
            limit: 500,
            max_bytes: 64 * 1024,
        );

        expect($result)->toContain('line500');
        expect($result)->not->toContain('Use offset=');
    });

    it('returns an explicitly requested full file larger than the sandbox capture cap', function () {
        $content = str_repeat("0123456789abcdef\n", 80_000);
        file_put_contents($this->tempDir . '/large-full-read.txt', $content);

        $tool = new ReadFileTool(baseDir: $this->tempDir);
        $result = $tool(
            $this->tempDir . '/large-full-read.txt',
            limit: 80_000,
            max_bytes: 2 * 1024 * 1024,
        );

        expect($result)->toContain("     1\t0123456789abcdef");
        expect($result)->toContain(" 80000\t0123456789abcdef");
        expect($result)->not->toContain('Use offset=');
    });

    it('does not suggest another page when exactly 200 newline-terminated lines were read', function () {
        $lines = array_map(
            static fn(int $line): string => "line{$line}",
            range(1, 200),
        );
        file_put_contents($this->tempDir . '/exact-page.txt', implode("\n", $lines) . "\n");

        $tool = new ReadFileTool(baseDir: $this->tempDir);
        $result = $tool($this->tempDir . '/exact-page.txt');

        expect($result)->toContain('line200');
        expect($result)->not->toContain('Use offset=200 to continue');
    });

    it('returns a recoverable preview instead of silently truncating a long line', function () {
        $longLine = str_repeat('x', 40 * 1024);
        file_put_contents($this->tempDir . '/long-line.txt', $longLine);

        $tool = new ReadFileTool(baseDir: $this->tempDir);
        $result = $tool($this->tempDir . '/long-line.txt');

        expect($result)->toContain(str_repeat('x', 1024));
        expect($result)->toContain('Line 1 exceeds the 32768-byte output limit');
        expect($result)->toContain('Retry with a larger max_bytes value');
        expect($result)->toContain('in a 40960-byte file');
        expect($result)->toContain('dd if=');
        expect($result)->toContain('bs=1 skip=4096 count=4096');
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

        expect($schema->name())->toBe('read_file');
        expect($schema->parameters())->toBeArray();
    });
});
