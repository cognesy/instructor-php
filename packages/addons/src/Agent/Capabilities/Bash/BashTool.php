<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\Bash;

use Cognesy\Addons\Agent\Tools\BaseTool;
use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;
use Cognesy\Utils\Sandbox\Execution\SandboxedExecution;
use Cognesy\Utils\Sandbox\Result\ExecutionResult;
use Cognesy\Utils\Sandbox\Sandbox;
use Cognesy\Utils\Sandbox\Contracts\CanStreamCommand;

class BashTool extends BaseTool
{
    private const DEFAULT_TIMEOUT = 120;
    private const DEFAULT_STDOUT_LIMIT = 5 * 1024 * 1024; // 5MB
    private const DEFAULT_STDERR_LIMIT = 1 * 1024 * 1024; // 1MB
    private const DANGEROUS_PATTERNS = [
        'rm -rf /',
        'mkfs',
        'shutdown',
        'reboot',
        'dd if=/dev/zero',
        '>:',
    ];

    private CanStreamCommand $sandbox;
    private BashPolicy $outputPolicy;

    public function __construct(
        ?ExecutionPolicy $policy = null,
        ?string $baseDir = null,
        int $timeout = self::DEFAULT_TIMEOUT,
        ?BashPolicy $outputPolicy = null,
    ) {
        parent::__construct(
            name: 'bash',
            description: <<<'DESC'
Execute a bash command and return stdout/stderr. Use for shell operations, not file reading.

Examples:
- "git status" → check git state
- "composer install" → install dependencies
- "php artisan migrate" → run migrations
- "grep -r 'TODO' src/" → search file contents
- "npm run build" → run build scripts

Prefer dedicated tools when available: read_file over cat, search_files over find.
DESC,
        );

        $baseDir = $baseDir ?? getcwd() ?: '/tmp';
        $policy = $policy ?? ExecutionPolicy::in($baseDir)
            ->withTimeout($timeout)
            ->withNetwork(false)
            ->withOutputCaps(self::DEFAULT_STDOUT_LIMIT, self::DEFAULT_STDERR_LIMIT)
            ->inheritEnvironment();

        $this->sandbox = Sandbox::host($policy);
        $this->outputPolicy = $outputPolicy ?? new BashPolicy();
    }

    public static function inDirectory(
        string $baseDir,
        int $timeout = self::DEFAULT_TIMEOUT,
        ?BashPolicy $outputPolicy = null,
    ): self {
        return new self(baseDir: $baseDir, timeout: $timeout, outputPolicy: $outputPolicy);
    }

    public static function withPolicy(ExecutionPolicy $policy, ?BashPolicy $outputPolicy = null): self {
        return new self(policy: $policy, outputPolicy: $outputPolicy);
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $command = $args['command'] ?? $args[0] ?? '';

        if ($this->isDangerousCommand($command)) {
            return 'Error: Command blocked by safety policy';
        }

        $result = $this->sandbox->execute(
            argv: ['bash', '-c', $command],
            stdin: null,
        );

        return $this->formatResult(
            command: $command,
            stdout: $result->stdout(),
            stderr: $result->stderr(),
            exitCode: $result->exitCode(),
            timedOut: $result->timedOut(),
            duration: $result->duration(),
            truncatedStdout: $result->truncatedStdout(),
            truncatedStderr: $result->truncatedStderr(),
        );
    }

    private function formatResult(
        string $command,
        string $stdout,
        string $stderr,
        int $exitCode,
        bool $timedOut,
        float $duration,
        bool $truncatedStdout,
        bool $truncatedStderr,
    ): string {
        $parts = [];

        if ($timedOut) {
            $parts[] = "⏱️ Command timed out after {$duration}s";
        }

        if ($exitCode !== 0) {
            $parts[] = "Exit code: {$exitCode}";
        }

        if ($stdout !== '') {
            $output = $this->truncateOutput($stdout, $truncatedStdout);
            $parts[] = $output;
        }

        if ($stderr !== '') {
            $error = $this->truncateOutput($stderr, $truncatedStderr);
            $parts[] = "STDERR:\n{$error}";
        }

        if (empty($parts)) {
            return "(no output)";
        }

        return implode("\n\n", $parts);
    }

    private function truncateOutput(string $output, bool $wasTruncated): string {
        $maxChars = $this->outputPolicy->maxOutputChars;
        if ($maxChars <= 0) {
            return $output;
        }

        $truncated = $wasTruncated || (strlen($output) > $maxChars);

        if (strlen($output) > $maxChars) {
            $output = $this->headTailOutput($output, $maxChars);
        }

        if ($truncated) {
            return "...(truncated)...\n" . $output;
        }

        return $output;
    }

    private function headTailOutput(string $output, int $maxChars): string {
        $head = max(0, min($this->outputPolicy->headChars, $maxChars));
        $tail = max(0, min($this->outputPolicy->tailChars, $maxChars - $head));

        if ($head + $tail < $maxChars) {
            $tail = $maxChars - $head;
        }

        if ($head === 0) {
            return substr($output, -$tail);
        }

        if ($tail === 0) {
            return substr($output, 0, $head);
        }

        return substr($output, 0, $head) . "\n...\n" . substr($output, -$tail);
    }

    private function isDangerousCommand(string $command): bool {
        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            if (stripos($command, $pattern) !== false) {
                return true;
            }
        }
        return false;
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
                        'command' => [
                            'type' => 'string',
                            'description' => 'The bash command to execute',
                        ],
                    ],
                    'required' => ['command'],
                ],
            ],
        ];
    }
}
