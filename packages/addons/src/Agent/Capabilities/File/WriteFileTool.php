<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\File;

use Cognesy\Addons\Agent\Tools\BaseTool;
use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;
use Cognesy\Utils\Sandbox\Sandbox;
use Cognesy\Utils\Sandbox\Contracts\CanExecuteCommand;

class WriteFileTool extends BaseTool
{
    private CanExecuteCommand $sandbox;

    public function __construct(
        ?ExecutionPolicy $policy = null,
        ?string $baseDir = null,
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

        $baseDir = $baseDir ?? getcwd() ?: '/tmp';
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
        $path = $args['path'] ?? $args[0] ?? '';
        $content = $args['content'] ?? $args[1] ?? '';

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
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => [
                            'type' => 'string',
                            'description' => 'The path to the file to write',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'The content to write to the file',
                        ],
                    ],
                    'required' => ['path', 'content'],
                ],
            ],
        ];
    }
}
