<?php

declare(strict_types=1);

namespace Cognesy\Agents\Capability\File;

use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Sandbox\Sandbox;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
use Override;

class WriteFileTool extends SimpleTool
{
    private const STREAM_CHUNK_BYTES = 64 * 1024;

    private CanExecuteCommand $sandbox;

    public function __construct(
        ?ExecutionPolicy $policy = null,
        string $baseDir = '',
        string $name = 'write_file',
    ) {
        parent::__construct(new WriteFileToolDescriptor($name));

        $policy = $policy ?? ExecutionPolicy::in($baseDir)
            ->withTimeout(30)
            ->withWritablePaths($baseDir)
            ->inheritEnvironment();

        $this->sandbox = Sandbox::host($policy);
    }

    public static function inDirectory(string $baseDir, string $name = 'write_file'): self {
        return new self(baseDir: $baseDir, name: $name);
    }

    public static function fromPolicy(ExecutionPolicy $policy, string $name = 'write_file'): self {
        return new self(policy: $policy, name: $name);
    }

    #[Override]
    public function __invoke(mixed ...$args): string {
        $path = $this->arg($args, 'path', 0, '');
        $content = $this->arg($args, 'content', 1, '');

        if (!$this->isValidPath($path)) {
            return 'Error: Invalid file path';
        }

        return $this->writeFile($path, $content);
    }

    private function isValidPath(string $path): bool {
        return !str_contains($path, "\0");
    }

    private function writeFile(string $path, string $content): string {
        $script = <<<'PHP'
$path = $argv[1];
$chunkBytes = (int) $argv[2];
$directory = dirname($path);

if (!is_dir($directory)) {
    fwrite(STDERR, "WRITE_TOOL_ERROR parent_missing\n");
    exit(2);
}

$temporaryPath = @tempnam($directory, '.agent-write-');
if ($temporaryPath === false) {
    fwrite(STDERR, "WRITE_TOOL_ERROR cannot_create_temp\n");
    exit(1);
}

$target = @fopen($temporaryPath, 'wb');
if ($target === false) {
    @unlink($temporaryPath);
    fwrite(STDERR, "WRITE_TOOL_ERROR cannot_write\n");
    exit(1);
}

$hash = hash_init('sha256');
$bytes = 0;
$newlines = 0;
$failed = false;

while (!feof(STDIN)) {
    $chunk = fread(STDIN, $chunkBytes);
    if ($chunk === false) {
        $failed = true;
        break;
    }

    $offset = 0;
    $length = strlen($chunk);
    while ($offset < $length) {
        $written = fwrite($target, substr($chunk, $offset));
        if ($written === false || $written === 0) {
            $failed = true;
            break 2;
        }
        $offset += $written;
    }

    hash_update($hash, $chunk);
    $bytes += $length;
    $newlines += substr_count($chunk, "\n");
}

$failed = !fclose($target) || $failed;
if ($failed) {
    @unlink($temporaryPath);
    fwrite(STDERR, "WRITE_TOOL_ERROR io_failure\n");
    exit(1);
}

$contentHash = hash_final($hash);
$lineCount = $newlines + 1;

$isIdentical = static function (string $candidate) use ($bytes, $contentHash): bool {
    if (!is_file($candidate) || filesize($candidate) !== $bytes) {
        return false;
    }
    $candidateHash = @hash_file('sha256', $candidate);
    return $candidateHash !== false && hash_equals($contentHash, $candidateHash);
};

clearstatcache(true, $path);
if (file_exists($path) || is_link($path)) {
    $identical = $isIdentical($path);
    @unlink($temporaryPath);
    if ($identical) {
        fwrite(STDOUT, "WRITE_TOOL_NOOP {$bytes} {$lineCount}\n");
        exit(0);
    }
    fwrite(STDERR, "WRITE_TOOL_ERROR target_exists\n");
    exit(3);
}

$mask = umask();
umask($mask);
@chmod($temporaryPath, 0666 & ~$mask);

if (@link($temporaryPath, $path)) {
    @unlink($temporaryPath);
    fwrite(STDOUT, "WRITE_TOOL_OK {$bytes} {$lineCount}\n");
    exit(0);
}

clearstatcache(true, $path);
$identical = $isIdentical($path);
@unlink($temporaryPath);
if ($identical) {
    fwrite(STDOUT, "WRITE_TOOL_NOOP {$bytes} {$lineCount}\n");
    exit(0);
}

if (file_exists($path) || is_link($path)) {
    fwrite(STDERR, "WRITE_TOOL_ERROR target_exists\n");
    exit(3);
}

fwrite(STDERR, "WRITE_TOOL_ERROR commit_failed\n");
exit(1);
PHP;

        $result = $this->sandbox->execute(
            argv: [PHP_BINARY, '-r', $script, $path, (string) self::STREAM_CHUNK_BYTES],
            stdin: $content,
        );

        $metadata = trim($result->stdout());
        if ($result->success() && preg_match('/^WRITE_TOOL_OK (\d+) (\d+)$/', $metadata, $matches) === 1) {
            return "Successfully wrote {$matches[1]} bytes ({$matches[2]} lines) to {$path} (streamed atomically)";
        }

        if ($result->success() && preg_match('/^WRITE_TOOL_NOOP (\d+) (\d+)$/', $metadata, $matches) === 1) {
            return "No change: {$path} already contains the requested {$matches[1]} bytes ({$matches[2]} lines)";
        }

        return match (trim($result->stderr())) {
            'WRITE_TOOL_ERROR parent_missing' => 'Error: Parent directory does not exist. Create it explicitly before writing the file.',
            'WRITE_TOOL_ERROR target_exists' => 'Error: Target already exists with different content. Use the edit tool for a bounded change; the existing file was not changed.',
            'WRITE_TOOL_ERROR cannot_create_temp' => 'Error: Cannot create a temporary file beside the target.',
            'WRITE_TOOL_ERROR cannot_write', 'WRITE_TOOL_ERROR io_failure' => 'Error: Failed while streaming content to a temporary file; the target was not changed.',
            'WRITE_TOOL_ERROR commit_failed' => 'Error: Failed to atomically create the target; no target file was created.',
            default => 'Error: Failed to write file' . ($result->stderr() !== '' ? ' - ' . trim($result->stderr()) : ''),
        };
    }

    #[Override]
    public function toToolSchema(): \Cognesy\Polyglot\Inference\Data\ToolDefinition {
        return \Cognesy\Polyglot\Inference\Data\ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('path', 'The path of a new file; its parent directory must already exist'),
                    JsonSchema::string('content', 'The content to write to the file'),
                ])
                ->withRequiredProperties(['path', 'content']),
        )->toArray());
    }
}
