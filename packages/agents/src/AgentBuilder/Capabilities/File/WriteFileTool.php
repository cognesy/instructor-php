<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\File;

use Cognesy\Agents\Core\Tools\BaseTool;
use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Sandbox\Sandbox;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

class WriteFileTool extends BaseTool
{
    private CanExecuteCommand $sandbox;

    public function __construct(
        ?ExecutionPolicy $policy = null,
        string $baseDir = '',
    ) {
        parent::__construct(
            name: 'write_file',
            description: <<<'DESC'
Write content to a file. Creates file and parent directories if needed. Overwrites existing files.

Examples:
- path="config.json", content='{"debug": true}'
- path="src/NewClass.php", content="<?php\n\nclass NewClass {}\n"

Use edit_file for partial changes. write_file replaces entire file content.
DESC,
        );

        $policy = $policy ?? ExecutionPolicy::in($baseDir)
            ->withTimeout(30)
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
        $path = $this->arg($args, 'path', 0, '');
        $content = $this->arg($args, 'content', 1, '');

        if (!$this->isValidPath($path)) {
            return "Error: Invalid file path";
        }

        $this->ensureParentDirectory($path);

        return $this->writeFile($path, $content);
    }

    private function isValidPath(string $path): bool {
        return !str_contains($path, "\0");
    }

    private function ensureParentDirectory(string $path): void {
        $dir = dirname($path);
        if ($dir !== '.' && $dir !== '/') {
            $this->sandbox->execute(
                argv: ['mkdir', '-p', $dir],
            );
        }
    }

    private function writeFile(string $path, string $content): string {
        $result = $this->sandbox->execute(
            argv: ['tee', $path],
            stdin: $content,
        );

        if (!$result->success()) {
            $error = $result->stderr() ?: "Failed to write file";
            return "Error: {$error}";
        }

        $lineCount = substr_count($content, "\n") + 1;
        $byteCount = strlen($content);

        return "Successfully wrote {$byteCount} bytes ({$lineCount} lines) to {$path}";
    }

    #[\Override]
    public function toToolSchema(): array {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('path', 'The path to the file to write'),
                    JsonSchema::string('content', 'The content to write to the file'),
                ])
                ->withRequiredProperties(['path', 'content'])
        )->toArray();
    }
}
