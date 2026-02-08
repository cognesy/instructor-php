<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\File;

use Cognesy\Agents\Core\Tools\BaseTool;
use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Sandbox\Sandbox;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

class ReadFileTool extends BaseTool
{
    private const DEFAULT_LINE_LIMIT = 2000;
    private const MAX_LINE_LENGTH = 2000;

    private CanExecuteCommand $sandbox;

    public function __construct(
        ?ExecutionPolicy $policy = null,
        string $baseDir = '',
    ) {
        parent::__construct(
            name: 'read_file',
            description: <<<'DESC'
Read the contents of a file with line numbers. Use to examine file content after finding it with search_files.

Examples:
- "composer.json" → read composer.json from project root
- "src/Config.php" → read specific file by path
- {"path": "large.log", "offset": 100, "limit": 50} → read lines 101-150

Returns numbered lines. For large files, use offset/limit to read specific sections.
DESC,
        );

        $policy = $policy ?? ExecutionPolicy::in($baseDir)
            ->withTimeout(30)
            ->withReadablePaths($baseDir)
            ->inheritEnvironment();

        $this->sandbox = Sandbox::host($policy);
    }

    public static function inDirectory(string $baseDir): self {
        return new self(baseDir: $baseDir);
    }

    public static function withPolicy(ExecutionPolicy $policy): self {
        return new self(policy: $policy);
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        // Handle array-wrapped args: [{"path": "..."}] -> {"path": "..."}
        $args = $this->unwrapArgs($args);

        $path = $this->arg($args, 'path', 0, '');
        $offset = $this->arg($args, 'offset', 1, 0);
        $limit = $this->arg($args, 'limit', 2, self::DEFAULT_LINE_LIMIT);

        if (!$this->isValidPath($path)) {
            return "Error: Invalid file path";
        }

        if (is_dir($path)) {
            return "Error: '{$path}' is a directory, not a file. Use list_dir to see directory contents.";
        }

        $limit = min($limit, self::DEFAULT_LINE_LIMIT);
        $offset = max(0, $offset);

        if ($this->isBinaryFile($path)) {
            return "Error: Cannot read binary file";
        }

        return $this->readFileWithLineNumbers($path, $offset, $limit);
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

    private function readFileWithLineNumbers(string $path, int $offset, int $limit): string {
        $startLine = $offset + 1;
        $endLine = $offset + $limit;

        $result = $this->sandbox->execute(
            argv: ['sed', '-n', "{$startLine},{$endLine}p", $path],
        );

        if (!$result->success()) {
            $error = $result->stderr() ?: "Failed to read file";
            return "Error: {$error}";
        }

        $content = $result->stdout();
        if ($content === '') {
            if ($offset > 0) {
                return "Error: Offset beyond end of file";
            }
            return "(empty file)";
        }

        return $this->formatWithLineNumbers($content, $startLine);
    }

    private function formatWithLineNumbers(string $content, int $startLine): string {
        $lines = explode("\n", $content);
        $formatted = [];
        $lineNumber = $startLine;

        foreach ($lines as $line) {
            if (strlen($line) > self::MAX_LINE_LENGTH) {
                $line = substr($line, 0, self::MAX_LINE_LENGTH) . '...';
            }
            $formatted[] = sprintf("%6d\t%s", $lineNumber, $line);
            $lineNumber++;
        }

        return implode("\n", $formatted);
    }

    #[\Override]
    public function toToolSchema(): array {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('path', 'File path (relative to project root or absolute). Example: "composer.json" or "src/Config.php"'),
                    JsonSchema::integer('offset', 'Line number to start reading from (0-indexed)'),
                    JsonSchema::integer('limit', 'Maximum number of lines to read (default: 2000)'),
                ])
                ->withRequiredProperties(['path'])
        )->toArray();
    }
}
