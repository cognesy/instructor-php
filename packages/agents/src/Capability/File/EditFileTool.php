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

class EditFileTool extends SimpleTool
{
    private const STREAM_CHUNK_BYTES = 64 * 1024;

    private CanExecuteCommand $sandbox;

    public function __construct(
        ?ExecutionPolicy $policy = null,
        string $baseDir = '',
        string $name = 'edit_file',
    ) {
        parent::__construct(new EditFileToolDescriptor($name));

        $policy = $policy ?? ExecutionPolicy::in($baseDir)
            ->withTimeout(30)
            ->withReadablePaths($baseDir)
            ->withWritablePaths($baseDir)
            ->inheritEnvironment();

        $this->sandbox = Sandbox::host($policy);
    }

    public static function inDirectory(string $baseDir, string $name = 'edit_file'): self {
        return new self(baseDir: $baseDir, name: $name);
    }

    public static function fromPolicy(ExecutionPolicy $policy, string $name = 'edit_file'): self {
        return new self(policy: $policy, name: $name);
    }

    #[Override]
    public function __invoke(mixed ...$args): string {
        $path = (string) $this->arg($args, 'path', 0, '');
        $old_string = (string) $this->arg($args, 'old_string', 1, '');
        $new_string = (string) $this->arg($args, 'new_string', 2, '');
        $replace_all = (bool) $this->arg($args, 'replace_all', 3, false);

        if (!$this->isValidPath($path)) {
            return 'Error: Invalid file path';
        }

        if ($old_string === $new_string) {
            return 'Error: old_string and new_string are identical';
        }

        if ($old_string === '') {
            return 'Error: old_string cannot be empty';
        }

        return $this->replaceInFile(
            path: $path,
            oldString: $old_string,
            newString: $new_string,
            replaceAll: $replace_all,
        );
    }

    private function isValidPath(string $path): bool {
        return !str_contains($path, "\0");
    }

    private function replaceInFile(
        string $path,
        string $oldString,
        string $newString,
        bool $replaceAll,
    ): string {
        $script = <<<'PHP'
$path = $argv[1];
$chunkBytes = (int) $argv[2];
$request = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$old = base64_decode((string) ($request['old'] ?? ''), true);
$new = base64_decode((string) ($request['new'] ?? ''), true);
$replaceAll = (bool) ($request['replaceAll'] ?? false);

if ($old === false || $new === false || $old === '') {
    fwrite(STDERR, "EDIT_TOOL_ERROR invalid_request\n");
    exit(2);
}

$source = @fopen($path, 'rb');
if ($source === false) {
    fwrite(STDERR, "EDIT_TOOL_ERROR cannot_read\n");
    exit(1);
}

$initialStat = fstat($source);
$directory = dirname($path);
$temporaryPath = @tempnam($directory, '.agent-edit-');
if ($temporaryPath === false) {
    fclose($source);
    fwrite(STDERR, "EDIT_TOOL_ERROR cannot_create_temp\n");
    exit(1);
}

$target = @fopen($temporaryPath, 'wb');
if ($target === false) {
    fclose($source);
    @unlink($temporaryPath);
    fwrite(STDERR, "EDIT_TOOL_ERROR cannot_write\n");
    exit(1);
}

$write = static function ($handle, string $content): bool {
    $offset = 0;
    $length = strlen($content);
    while ($offset < $length) {
        $written = fwrite($handle, substr($content, $offset));
        if ($written === false || $written === 0) {
            return false;
        }
        $offset += $written;
    }
    return true;
};

$buffer = '';
$occurrences = 0;
$oldLength = strlen($old);
$failed = false;

while (!feof($source)) {
    $chunk = fread($source, $chunkBytes);
    if ($chunk === false) {
        $failed = true;
        break;
    }

    $buffer .= $chunk;
    while (($position = strpos($buffer, $old)) !== false) {
        $failed = !$write($target, substr($buffer, 0, $position))
            || !$write($target, $new);
        if ($failed) {
            break 2;
        }
        $occurrences++;
        $buffer = substr($buffer, $position + $oldLength);
    }

    if (!feof($source)) {
        $keepBytes = $oldLength - 1;
        $writeBytes = max(0, strlen($buffer) - $keepBytes);
        $failed = !$write($target, substr($buffer, 0, $writeBytes));
        $buffer = substr($buffer, $writeBytes);
    }
}

if (!$failed) {
    $failed = !$write($target, $buffer);
}

$finalStat = fstat($source);
fclose($source);
$failed = !fclose($target) || $failed;

if ($failed) {
    @unlink($temporaryPath);
    fwrite(STDERR, "EDIT_TOOL_ERROR io_failure\n");
    exit(1);
}

if ($occurrences === 0) {
    @unlink($temporaryPath);
    fwrite(STDERR, "EDIT_TOOL_ERROR not_found\n");
    exit(3);
}

if ($occurrences > 1 && !$replaceAll) {
    @unlink($temporaryPath);
    fwrite(STDERR, "EDIT_TOOL_ERROR multiple {$occurrences}\n");
    exit(4);
}

$sourceChanged = ($initialStat['size'] ?? null) !== ($finalStat['size'] ?? null)
    || ($initialStat['mtime'] ?? null) !== ($finalStat['mtime'] ?? null);
if ($sourceChanged) {
    @unlink($temporaryPath);
    fwrite(STDERR, "EDIT_TOOL_ERROR source_changed\n");
    exit(5);
}

@chmod($temporaryPath, (int) ($initialStat['mode'] ?? 0644) & 0777);
if (!@rename($temporaryPath, $path)) {
    @unlink($temporaryPath);
    fwrite(STDERR, "EDIT_TOOL_ERROR commit_failed\n");
    exit(1);
}

$fileBytes = (int) ($initialStat['size'] ?? 0);
fwrite(STDOUT, "EDIT_TOOL_OK {$occurrences} {$fileBytes}\n");
PHP;

        $request = json_encode([
            'old' => base64_encode($oldString),
            'new' => base64_encode($newString),
            'replaceAll' => $replaceAll,
        ], JSON_THROW_ON_ERROR);

        $result = $this->sandbox->execute(
            argv: [PHP_BINARY, '-r', $script, $path, (string) self::STREAM_CHUNK_BYTES],
            stdin: $request,
        );

        $metadata = trim($result->stdout());
        if ($result->success() && preg_match('/^EDIT_TOOL_OK (\d+) (\d+)$/', $metadata, $matches) === 1) {
            $occurrences = (int) $matches[1];
            $fileBytes = (int) $matches[2];

            return "Successfully replaced {$occurrences} occurrence(s) in {$path} ({$fileBytes}-byte source, streamed atomically)";
        }

        return $this->editError(trim($result->stderr()));
    }

    private function editError(string $error): string {
        if (preg_match('/^EDIT_TOOL_ERROR multiple (\d+)$/', $error, $matches) === 1) {
            return "Error: old_string found {$matches[1]} times. Use replace_all=true to replace all, or provide more context to make the match unique. The file was not changed.";
        }

        return match ($error) {
            'EDIT_TOOL_ERROR cannot_read' => 'Error: Cannot read file - file not found or access denied',
            'EDIT_TOOL_ERROR not_found' => 'Error: old_string not found in file. The file was not changed.',
            'EDIT_TOOL_ERROR source_changed' => 'Error: File changed while it was being edited. Retry against the latest content; the file was not changed by this tool.',
            'EDIT_TOOL_ERROR cannot_create_temp' => 'Error: Cannot create a temporary file beside the target. The file was not changed.',
            'EDIT_TOOL_ERROR cannot_write', 'EDIT_TOOL_ERROR io_failure' => 'Error: Failed while streaming the edited content. The file was not changed.',
            'EDIT_TOOL_ERROR commit_failed' => 'Error: Failed to atomically commit the edit. The file was not changed.',
            default => 'Error: Failed to edit file' . ($error !== '' ? " - {$error}" : ''),
        };
    }

    #[Override]
    public function toToolSchema(): \Cognesy\Polyglot\Inference\Data\ToolDefinition {
        return \Cognesy\Polyglot\Inference\Data\ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('path', 'The path to the file to edit'),
                    JsonSchema::string('old_string', 'Exact string to find (include whitespace). Must be unique unless using replace_all'),
                    JsonSchema::string('new_string', 'Replacement string. Can be empty to delete old_string'),
                    JsonSchema::boolean('replace_all', 'If true, replace all occurrences. If false (default), old_string must be unique.'),
                ])
                ->withRequiredProperties(['path', 'old_string', 'new_string']),
        )->toArray());
    }
}
