<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\File;

use Cognesy\Agents\Core\Tools\BaseTool;
use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Sandbox\Sandbox;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

class EditFileTool extends BaseTool
{
    private CanExecuteCommand $sandbox;

    public function __construct(
        ?ExecutionPolicy $policy = null,
        string $baseDir = '',
    ) {
        parent::__construct(
            name: 'edit_file',
            description: <<<'DESC'
Edit a file by replacing exact string matches. old_string must match exactly including whitespace.

Examples:
- Fix typo: old_string="teh", new_string="the"
- Change function: old_string="function old()", new_string="function new()"
- Update config: old_string='"debug": false', new_string='"debug": true'
- Rename all: old_string="OldClass", new_string="NewClass", replace_all=true

IMPORTANT: Include enough context in old_string to make it unique. If multiple matches exist, use replace_all=true or provide more surrounding code.
DESC,
        );

        $policy = $policy ?? ExecutionPolicy::in($baseDir)
            ->withTimeout(30)
            ->withReadablePaths($baseDir)
            ->withWritablePaths($baseDir)
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
        $path = (string) $this->arg($args, 'path', 0, '');
        $old_string = (string) $this->arg($args, 'old_string', 1, '');
        $new_string = (string) $this->arg($args, 'new_string', 2, '');
        $replace_all = (bool) $this->arg($args, 'replace_all', 3, false);

        if (!$this->isValidPath($path)) {
            return "Error: Invalid file path";
        }

        if ($old_string === $new_string) {
            return "Error: old_string and new_string are identical";
        }

        if ($old_string === '') {
            return "Error: old_string cannot be empty";
        }

        $readResult = $this->sandbox->execute(
            argv: ['cat', $path],
        );

        if (!$readResult->success()) {
            return "Error: Cannot read file - " . ($readResult->stderr() ?: "file not found");
        }

        $content = $readResult->stdout();
        $occurrences = substr_count($content, $old_string);

        if ($occurrences === 0) {
            return "Error: old_string not found in file";
        }

        if ($occurrences > 1 && !$replace_all) {
            return "Error: old_string found {$occurrences} times. Use replace_all=true to replace all, or provide more context to make the match unique.";
        }

        if ($replace_all) {
            $newContent = str_replace($old_string, $new_string, $content);
        } else {
            $pos = strpos($content, $old_string);
            if ($pos === false) {
                return "Error: old_string not found in file (this should not happen after validation)";
            }
            $newContent = substr($content, 0, $pos) . $new_string . substr($content, $pos + strlen($old_string));
        }

        $writeResult = $this->sandbox->execute(
            argv: ['tee', $path],
            stdin: $newContent,
        );

        if (!$writeResult->success()) {
            return "Error: Failed to write file - " . $writeResult->stderr();
        }

        $replacementCount = $replace_all ? $occurrences : 1;
        return "Successfully replaced {$replacementCount} occurrence(s) in {$path}";
    }

    private function isValidPath(string $path): bool {
        return !str_contains($path, "\0");
    }

    #[\Override]
    public function toToolSchema(): array {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('path', 'The path to the file to edit'),
                    JsonSchema::string('old_string', 'Exact string to find (include whitespace). Must be unique unless using replace_all'),
                    JsonSchema::string('new_string', 'Replacement string. Can be empty to delete old_string'),
                    JsonSchema::boolean('replace_all', 'If true, replace all occurrences. If false (default), old_string must be unique.'),
                ])
                ->withRequiredProperties(['path', 'old_string', 'new_string'])
        )->toArray();
    }
}
