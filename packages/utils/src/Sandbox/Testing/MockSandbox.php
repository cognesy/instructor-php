<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Testing;

use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;
use Cognesy\Utils\Sandbox\Contracts\CanStreamCommand;
use Cognesy\Utils\Sandbox\Data\ExecResult;
use RuntimeException;

final class MockSandbox implements CanStreamCommand
{
    /** @var array<int, list<string>> */
    private array $commands = [];
    /** @var array<string, list<ExecResult>> */
    private array $responses;
    private ?ExecResult $defaultResponse;

    /**
     * @param array<string, list<ExecResult|array<string, mixed>>> $responses
     */
    public function __construct(
        private readonly ExecutionPolicy $policy,
        array $responses = [],
        ?ExecResult $defaultResponse = null,
    ) {
        $this->responses = $this->normalizeResponses($responses);
        $this->defaultResponse = $defaultResponse;
    }

    public static function withResponses(array $responses, ?ExecResult $defaultResponse = null): self {
        return new self(ExecutionPolicy::default(), $responses, $defaultResponse);
    }

    #[\Override]
    public function policy(): ExecutionPolicy {
        return $this->policy;
    }

    /** @return array<int, list<string>> */
    public function commands(): array {
        return $this->commands;
    }

    public function enqueue(string $commandKey, ExecResult $result): void {
        $this->responses[$commandKey][] = $result;
    }

    #[\Override]
    public function execute(array $argv, ?string $stdin = null): ExecResult {
        $this->record($argv, $stdin);
        return $this->nextResult($this->key($argv));
    }

    #[\Override]
    public function executeStreaming(array $argv, callable $onOutput, ?string $stdin = null): ExecResult {
        $this->record($argv, $stdin);
        $result = $this->nextResult($this->key($argv));

        $stdout = $result->stdout();
        if ($stdout !== '') {
            $onOutput('out', $stdout);
        }

        $stderr = $result->stderr();
        if ($stderr !== '') {
            $onOutput('err', $stderr);
        }

        return $result;
    }

    private function record(array $argv, ?string $stdin): void {
        $entry = $argv;
        if ($stdin !== null && $stdin !== '') {
            $entry[] = "[stdin={$stdin}]";
        }
        $this->commands[] = $entry;
    }

    private function key(array $argv): string {
        return implode(' ', $argv);
    }

    private function nextResult(string $key): ExecResult {
        $queued = $this->responses[$key] ?? [];
        if ($queued === []) {
            if ($this->defaultResponse !== null) {
                return $this->defaultResponse;
            }
            throw new RuntimeException("MockSandbox has no response for command: {$key}");
        }
        $result = array_shift($queued);
        $this->responses[$key] = $queued;
        return $result;
    }

    /**
     * @param array<string, list<ExecResult|array<string, mixed>>> $responses
     * @return array<string, list<ExecResult>>
     */
    private function normalizeResponses(array $responses): array {
        $normalized = [];
        foreach ($responses as $key => $queue) {
            $list = [];
            foreach ($queue as $item) {
                $list[] = $item instanceof ExecResult
                    ? $item
                    : $this->fromArray($item);
            }
            $normalized[$key] = $list;
        }
        return $normalized;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function fromArray(array $data): ExecResult {
        return new ExecResult(
            stdout: (string) ($data['stdout'] ?? ''),
            stderr: (string) ($data['stderr'] ?? ''),
            exitCode: (int) ($data['exit_code'] ?? 0),
            duration: (float) ($data['duration'] ?? 0.0),
            timedOut: (bool) ($data['timed_out'] ?? false),
            truncatedStdout: (bool) ($data['truncated_stdout'] ?? false),
            truncatedStderr: (bool) ($data['truncated_stderr'] ?? false),
        );
    }
}
