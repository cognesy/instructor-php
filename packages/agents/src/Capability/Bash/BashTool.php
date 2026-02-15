<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Bash;

use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Sandbox\Sandbox;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

final class BashTool extends SimpleTool
{
    private CanExecuteCommand $sandbox;
    private BashPolicy $outputPolicy;

    public function __construct(
        ?ExecutionPolicy $policy = null,
        string $baseDir = '',
        ?BashPolicy $outputPolicy = null,
        ?CanExecuteCommand $sandbox = null,
    ) {
        parent::__construct(new BashToolDescriptor());

        $this->outputPolicy = $outputPolicy ?? new BashPolicy();

        if ($sandbox !== null) {
            $this->sandbox = $sandbox;
        } else {
            $baseDir = $this->resolveDir($baseDir);
            $policy = $policy ?? ExecutionPolicy::in($baseDir)
                ->withTimeout($this->outputPolicy->timeout)
                ->withNetwork(false)
                ->withOutputCaps($this->outputPolicy->stdoutLimitBytes, $this->outputPolicy->stderrLimitBytes)
                ->inheritEnvironment();
            $this->sandbox = Sandbox::host($policy);
        }
    }

    public static function inDirectory(
        string $baseDir,
        ?BashPolicy $outputPolicy = null,
    ): self {
        return new self(
            baseDir: $baseDir,
            outputPolicy: $outputPolicy,
        );
    }

    public static function withPolicy(ExecutionPolicy $policy, ?BashPolicy $outputPolicy = null): self {
        return new self(
            policy: $policy,
            outputPolicy: $outputPolicy,
        );
    }

    public static function withSandbox(CanExecuteCommand $sandbox, ?BashPolicy $outputPolicy = null): self {
        return new self(
            outputPolicy: $outputPolicy,
            sandbox: $sandbox,
        );
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $command = (string) $this->arg($args, 'command', 0, '');

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

        if ($parts === []) {
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
        foreach ($this->outputPolicy->dangerousPatterns as $pattern) {
            if (stripos($command, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    #[\Override]
    public function toToolSchema(): array {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('command','The bash command to execute')
                ])
                ->withRequiredProperties(['command'])
        )->toArray();
    }

    private function resolveDir(string $baseDir) : string {
        return $baseDir !== '' ? $baseDir : getcwd();
    }
}
