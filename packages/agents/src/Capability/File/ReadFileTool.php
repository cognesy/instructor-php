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

class ReadFileTool extends SimpleTool
{
    private const DEFAULT_LINE_LIMIT = 200;
    private const MAX_OUTPUT_BYTES = 32 * 1024;
    private const LONG_LINE_PREVIEW_BYTES = 4 * 1024;

    private CanExecuteCommand $sandbox;

    public function __construct(
        ?ExecutionPolicy $policy = null,
        string $baseDir = '',
        string $name = 'read_file',
    ) {
        parent::__construct(new ReadFileToolDescriptor($name));

        $policy = $policy ?? ExecutionPolicy::in($baseDir)
            ->withTimeout(30)
            ->withReadablePaths($baseDir)
            ->inheritEnvironment();

        $this->sandbox = Sandbox::host($policy);
    }

    public static function inDirectory(string $baseDir, string $name = 'read_file'): self {
        return new self(baseDir: $baseDir, name: $name);
    }

    public static function fromPolicy(ExecutionPolicy $policy, string $name = 'read_file'): self {
        return new self(policy: $policy, name: $name);
    }

    #[Override]
    public function __invoke(mixed ...$args): string {
        // Handle array-wrapped args: [{"path": "..."}] -> {"path": "..."}
        $args = $this->unwrapArgs($args);

        $path = $this->arg($args, 'path', 0, '');
        $offset = (int) $this->arg($args, 'offset', 1, 0);
        $limit = (int) $this->arg($args, 'limit', 2, self::DEFAULT_LINE_LIMIT);
        $maxBytes = (int) $this->arg($args, 'max_bytes', 3, self::MAX_OUTPUT_BYTES);

        if (!$this->isValidPath($path)) {
            return 'Error: Invalid file path';
        }

        if (is_dir($path)) {
            return "Error: '{$path}' is a directory, not a file. Use a directory-listing tool or bash ls.";
        }

        $limit = max(1, $limit);
        $maxBytes = max(1, $maxBytes);
        $offset = max(0, $offset);

        if ($this->isBinaryFile($path)) {
            return 'Error: Cannot read binary file';
        }

        return $this->readFileWithLineNumbers($path, $offset, $limit, $maxBytes);
    }

    private function unwrapArgs(array $args): array {
        // Handle LLM wrapping args in array: [{"path": "..."}] -> {"path": "..."}
        if (isset($args[0]) && is_array($args[0]) && !isset($args['path'])) {
            return $args[0];
        }

        return $args;
    }

    private function isValidPath(string $path): bool {
        return !str_contains($path, "\0");
    }

    private function isBinaryFile(string $path): bool {
        $result = $this->sandbox->execute(
            argv: ['file', '--mime-type', '-b', $path],
        );

        if (!$result->success()) {
            return false;
        }

        $mimeType = trim($result->stdout());

        // Empty files and common text types are not binary
        $textTypes = ['text/', 'application/json', 'application/xml', 'application/javascript'];
        $nonBinaryTypes = ['application/x-empty', 'inode/x-empty'];

        foreach ($nonBinaryTypes as $type) {
            if ($mimeType === $type) {
                return false;
            }
        }

        foreach ($textTypes as $type) {
            if (str_starts_with($mimeType, $type)) {
                return false;
            }
        }

        return true;
    }

    private function readFileWithLineNumbers(string $path, int $offset, int $limit, int $maxBytes): string {
        $script = <<<'PHP'
$path = $argv[1];
$offset = (int) $argv[2];
$lineLimit = (int) $argv[3];
$byteLimit = (int) $argv[4];
$previewLimit = (int) $argv[5];
$chunkSize = 8192;

$handle = @fopen($path, 'rb');
if ($handle === false) {
    fwrite(STDERR, "READ_TOOL_ERROR cannot_open\n");
    exit(1);
}

$stat = fstat($handle);
$fileBytes = (int) ($stat['size'] ?? 0);
$skipped = 0;

while ($skipped < $offset && !feof($handle)) {
    $chunk = fgets($handle, $chunkSize + 1);
    if ($chunk === false) {
        break;
    }
    if (str_ends_with($chunk, "\n")) {
        $skipped++;
    }
}

if ($skipped < $offset) {
    fwrite(STDERR, "READ_TOOL_META beyond {$fileBytes}\n");
    exit(0);
}

$output = '';
$lineCount = 0;

while ($lineCount < $lineLimit && !feof($handle)) {
    $lineStartByte = ftell($handle);
    $line = '';

    while (!feof($handle)) {
        $chunk = fgets($handle, $chunkSize + 1);
        if ($chunk === false) {
            break;
        }

        $line .= $chunk;
        if (str_ends_with($chunk, "\n")) {
            break;
        }

        if (strlen($line) > $byteLimit) {
            break;
        }
    }

    if ($line === '') {
        break;
    }

    $lineNumber = $offset + $lineCount + 1;
    $prefix = sprintf("%6d\t", $lineNumber);

    if ($lineCount === 0 && strlen($prefix) + strlen($line) > $byteLimit) {
        echo $prefix . substr($line, 0, $previewLimit);
        fwrite(STDERR, "READ_TOOL_META long {$lineNumber} {$fileBytes} {$lineStartByte}\n");
        exit(0);
    }

    if (strlen($output) + strlen($prefix) + strlen($line) > $byteLimit) {
        $nextOffset = $offset + $lineCount;
        echo $output;
        fwrite(STDERR, "READ_TOOL_META more {$nextOffset} {$fileBytes}\n");
        exit(0);
    }

    $output .= $prefix . $line;
    $lineCount++;
}

echo $output;

if ($lineCount === $lineLimit && fgetc($handle) !== false) {
    $nextOffset = $offset + $lineCount;
    fwrite(STDERR, "READ_TOOL_META more {$nextOffset} {$fileBytes}\n");
}
PHP;

        $stdout = '';
        $stderr = '';
        $result = $this->sandbox->execute(
            argv: [
                PHP_BINARY,
                '-r',
                $script,
                $path,
                (string) $offset,
                (string) $limit,
                (string) $maxBytes,
                (string) self::LONG_LINE_PREVIEW_BYTES,
            ],
            onOutput: static function (string $type, string $chunk) use (&$stdout, &$stderr): void {
                if ($type === 'out') {
                    $stdout .= $chunk;

                    return;
                }
                $stderr .= $chunk;
            },
        );

        if (!$result->success()) {
            $error = $result->stderr() ?: 'Failed to read file';

            return "Error: {$error}";
        }

        $content = $stdout !== '' ? $stdout : $result->stdout();
        $metadata = trim($stderr !== '' ? $stderr : $result->stderr());
        if ($content === '') {
            if (str_starts_with($metadata, 'READ_TOOL_META beyond ')) {
                return 'Error: Offset beyond end of file';
            }
            if ($offset > 0) {
                return 'Error: Offset beyond end of file';
            }

            return '(empty file)';
        }

        return $content . $this->truncationNotice(
            metadata: $metadata,
            path: $path,
            lineLimit: $limit,
            byteLimit: $maxBytes,
        );
    }

    private function truncationNotice(
        string $metadata,
        string $path,
        int $lineLimit,
        int $byteLimit,
    ): string {
        if (preg_match('/^READ_TOOL_META more (\d+) (\d+)$/', $metadata, $matches) === 1) {
            $nextOffset = (int) $matches[1];
            $fileBytes = (int) $matches[2];

            return "\n[Output limited to {$lineLimit} lines or {$byteLimit} bytes"
                . " from a {$fileBytes}-byte file. Use offset={$nextOffset} to continue.]";
        }

        if (preg_match('/^READ_TOOL_META long (\d+) (\d+) (\d+)$/', $metadata, $matches) === 1) {
            $line = (int) $matches[1];
            $fileBytes = (int) $matches[2];
            $lineStartByte = (int) $matches[3];
            $nextByte = $lineStartByte + self::LONG_LINE_PREVIEW_BYTES;
            $escapedPath = escapeshellarg($path);

            return "...\n\n[Line {$line} exceeds the {$byteLimit}"
                . "-byte output limit in a {$fileBytes}-byte file; showing the first "
                . self::LONG_LINE_PREVIEW_BYTES
                . " bytes. Retry with a larger max_bytes value or continue with: dd if={$escapedPath} bs=1 skip={$nextByte} count="
                . self::LONG_LINE_PREVIEW_BYTES
                . ' 2>/dev/null]';
        }

        return '';
    }

    #[Override]
    public function toToolSchema(): \Cognesy\Polyglot\Inference\Data\ToolDefinition {
        return \Cognesy\Polyglot\Inference\Data\ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('path', 'File path (relative to project root or absolute). Example: "composer.json" or "src/Config.php"'),
                    JsonSchema::integer('offset', 'Line number to start reading from (0-indexed)'),
                    JsonSchema::integer('limit', 'Maximum number of lines to read (default: 200; no fixed upper limit)'),
                    JsonSchema::integer('max_bytes', 'Maximum output bytes (default: 32768; increase explicitly for a larger or full-file read)'),
                ])
                ->withRequiredProperties(['path']),
        )->toArray());
    }
}
