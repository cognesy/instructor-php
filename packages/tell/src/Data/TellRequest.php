<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

use Closure;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;
use Cognesy\Tell\Capability\AskUser\TellAnswerQueue;
use Cognesy\Tell\Configuration\TellExecutionPolicy;
use Cognesy\Tell\Console\TellOptions;
use InvalidArgumentException;

final readonly class TellRequest
{
    /**
     * @param  list<string>  $tools
     * @param  list<Closure(TellEventEnvelope): void>  $listeners
     */
    public function __construct(
        public string $prompt,
        public string $directory = '',
        public string $agent = 'default',
        public string $connection = 'openai',
        public string $model = '',
        public ?ReasoningEffort $reasoningEffort = null,
        public string $dsn = '',
        public ?string $session = null,
        public ?string $branch = null,
        public array $tools = [],
        public TellAnswerQueue $answers = new TellAnswerQueue(),
        public int $maxSteps = 10,
        public TellExecutionMode $mode = TellExecutionMode::Stateless,
        private array $listeners = [],
        public bool $connectionExplicit = false,
        public bool $modelExplicit = false,
        public bool $reasoningEffortExplicit = false,
        public bool $toolsExplicit = false,
        /** @var array<string, int> */
        public array $policyOverrides = [],
        public ?TellExecutionPolicy $policy = null,
    ) {
        if ($prompt === '') {
            throw new InvalidArgumentException('Prompt must not be empty.');
        }
        if ($agent === '') {
            throw new InvalidArgumentException('Agent name must not be empty.');
        }
        if ($maxSteps < 1) {
            throw new InvalidArgumentException('Maximum steps must be at least 1.');
        }
        if ($directory !== '' && !is_dir($directory)) {
            throw new InvalidArgumentException("Working directory does not exist: {$directory}");
        }
    }

    public static function prompt(string $prompt): self {
        return new self(prompt: $prompt);
    }

    public static function fromOptions(TellOptions $options): self {
        return new self(
            prompt: $options->prompt,
            directory: $options->directory,
            agent: $options->agent,
            connection: $options->connection,
            model: $options->model,
            reasoningEffort: $options->reasoningEffort,
            dsn: $options->dsn,
            session: $options->session,
            branch: $options->branch,
            tools: $options->tools,
            answers: $options->answers,
            maxSteps: $options->maxSteps,
            mode: match (true) {
                $options->transient => TellExecutionMode::Transient,
                default => TellExecutionMode::Automatic,
            },
            connectionExplicit: $options->connectionExplicit,
            modelExplicit: $options->modelExplicit,
            reasoningEffortExplicit: $options->reasoningEffortExplicit,
            toolsExplicit: $options->toolsExplicit,
            policyOverrides: $options->policyOverrides,
            policy: $options->policy ?? TellExecutionPolicy::resolve([], $options->policyOverrides),
        );
    }

    public function withDirectory(string $directory): self {
        return new self(
            prompt: $this->prompt,
            directory: $directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
            reasoningEffort: $this->reasoningEffort,
            dsn: $this->dsn,
            session: $this->session,
            branch: $this->branch,
            tools: $this->tools,
            answers: $this->answers,
            maxSteps: $this->maxSteps,
            mode: $this->mode,
            listeners: $this->listeners,
            connectionExplicit: $this->connectionExplicit,
            modelExplicit: $this->modelExplicit,
            reasoningEffortExplicit: $this->reasoningEffortExplicit,
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy ?? TellExecutionPolicy::resolve([], $this->policyOverrides),
        );
    }

    public function agent(string $agent): self {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $agent,
            connection: $this->connection,
            model: $this->model,
            reasoningEffort: $this->reasoningEffort,
            dsn: $this->dsn,
            session: $this->session,
            branch: $this->branch,
            tools: $this->tools,
            answers: $this->answers,
            maxSteps: $this->maxSteps,
            mode: $this->mode,
            listeners: $this->listeners,
            connectionExplicit: $this->connectionExplicit,
            modelExplicit: $this->modelExplicit,
            reasoningEffortExplicit: $this->reasoningEffortExplicit,
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    public function connection(string $connection): self {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $connection,
            model: $this->model,
            reasoningEffort: $this->reasoningEffort,
            dsn: $this->dsn,
            session: $this->session,
            branch: $this->branch,
            tools: $this->tools,
            answers: $this->answers,
            maxSteps: $this->maxSteps,
            mode: $this->mode,
            listeners: $this->listeners,
            connectionExplicit: true,
            modelExplicit: $this->modelExplicit,
            reasoningEffortExplicit: $this->reasoningEffortExplicit,
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    public function model(string $model): self {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $model,
            reasoningEffort: $this->reasoningEffort,
            dsn: $this->dsn,
            session: $this->session,
            branch: $this->branch,
            tools: $this->tools,
            answers: $this->answers,
            maxSteps: $this->maxSteps,
            mode: $this->mode,
            listeners: $this->listeners,
            connectionExplicit: $this->connectionExplicit,
            modelExplicit: true,
            reasoningEffortExplicit: $this->reasoningEffortExplicit,
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    public function reasoningEffort(ReasoningEffort $effort): self {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
            reasoningEffort: $effort,
            dsn: $this->dsn,
            session: $this->session,
            branch: $this->branch,
            tools: $this->tools,
            answers: $this->answers,
            maxSteps: $this->maxSteps,
            mode: $this->mode,
            listeners: $this->listeners,
            connectionExplicit: $this->connectionExplicit,
            modelExplicit: $this->modelExplicit,
            reasoningEffortExplicit: true,
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    /** @param list<string> $tools */
    public function tools(array $tools): self {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
            reasoningEffort: $this->reasoningEffort,
            dsn: $this->dsn,
            session: $this->session,
            branch: $this->branch,
            tools: $tools,
            answers: $this->answers,
            maxSteps: $this->maxSteps,
            mode: $this->mode,
            listeners: $this->listeners,
            connectionExplicit: $this->connectionExplicit,
            modelExplicit: $this->modelExplicit,
            toolsExplicit: true,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    /** Supply bounded answers for the Tell-owned non-interactive ask_user tool. */
    public function withAnswers(TellAnswerQueue $answers): self {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
            reasoningEffort: $this->reasoningEffort,
            dsn: $this->dsn,
            session: $this->session,
            branch: $this->branch,
            tools: $this->tools,
            answers: $answers,
            maxSteps: $this->maxSteps,
            mode: $this->mode,
            listeners: $this->listeners,
            connectionExplicit: $this->connectionExplicit,
            modelExplicit: $this->modelExplicit,
            reasoningEffortExplicit: $this->reasoningEffortExplicit,
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    public function maxSteps(int $maxSteps): self {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
            reasoningEffort: $this->reasoningEffort,
            dsn: $this->dsn,
            session: $this->session,
            branch: $this->branch,
            tools: $this->tools,
            answers: $this->answers,
            maxSteps: $maxSteps,
            mode: $this->mode,
            listeners: $this->listeners,
            connectionExplicit: $this->connectionExplicit,
            modelExplicit: $this->modelExplicit,
            reasoningEffortExplicit: $this->reasoningEffortExplicit,
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    public function conversation(?string $session): self {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
            reasoningEffort: $this->reasoningEffort,
            dsn: $this->dsn,
            session: $session,
            branch: $this->branch,
            tools: $this->tools,
            answers: $this->answers,
            maxSteps: $this->maxSteps,
            mode: $this->mode,
            listeners: $this->listeners,
            connectionExplicit: $this->connectionExplicit,
            modelExplicit: $this->modelExplicit,
            reasoningEffortExplicit: $this->reasoningEffortExplicit,
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    public function branch(?string $branch): self {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
            reasoningEffort: $this->reasoningEffort,
            dsn: $this->dsn,
            session: $this->session,
            branch: $branch,
            tools: $this->tools,
            answers: $this->answers,
            maxSteps: $this->maxSteps,
            mode: $this->mode,
            listeners: $this->listeners,
            connectionExplicit: $this->connectionExplicit,
            modelExplicit: $this->modelExplicit,
            reasoningEffortExplicit: $this->reasoningEffortExplicit,
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    /** @param array<string, mixed> $values */
    public function withBranchConfig(array $values): self {
        $connection = $this->dsn === '' && !$this->connectionExplicit && isset($values['connection']) && is_string($values['connection'])
            ? $values['connection']
            : $this->connection;
        $model = $this->dsn === '' && !$this->modelExplicit && isset($values['model']) && is_string($values['model'])
            ? $values['model']
            : $this->model;
        $reasoningEffort = !$this->reasoningEffortExplicit && isset($values['reasoningEffort']) && is_string($values['reasoningEffort'])
            ? ReasoningEffort::parse($values['reasoningEffort'])
            : $this->reasoningEffort;
        $tools = !$this->toolsExplicit && isset($values['tools']) && is_array($values['tools'])
            ? array_values(array_filter($values['tools'], static fn (mixed $tool): bool => is_string($tool)))
            : $this->tools;

        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $connection,
            model: $model,
            reasoningEffort: $reasoningEffort,
            dsn: $this->dsn,
            session: $this->session,
            branch: $this->branch,
            tools: $tools,
            answers: $this->answers,
            maxSteps: $this->maxSteps,
            mode: $this->mode,
            listeners: $this->listeners,
            connectionExplicit: $this->connectionExplicit,
            modelExplicit: $this->modelExplicit,
            reasoningEffortExplicit: $this->reasoningEffortExplicit,
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: TellExecutionPolicy::resolve($values, $this->policyOverrides),
        );
    }

    /** @param callable(TellEventEnvelope): void $listener */
    public function onEvent(callable $listener): self {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
            reasoningEffort: $this->reasoningEffort,
            dsn: $this->dsn,
            session: $this->session,
            branch: $this->branch,
            tools: $this->tools,
            answers: $this->answers,
            maxSteps: $this->maxSteps,
            mode: $this->mode,
            listeners: [...$this->listeners, Closure::fromCallable($listener)],
            connectionExplicit: $this->connectionExplicit,
            modelExplicit: $this->modelExplicit,
            reasoningEffortExplicit: $this->reasoningEffortExplicit,
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    /** @return list<Closure(TellEventEnvelope): void> */
    public function listeners(): array {
        return $this->listeners;
    }

    public function transient(): self {
        return $this->withMode(TellExecutionMode::Transient, $this->session);
    }

    public function durable(?string $session = null): self {
        return $this->withMode(TellExecutionMode::Durable, $session ?? $this->session);
    }

    public function toOptions(): TellOptions {
        if ($this->directory === '') {
            throw new InvalidArgumentException('Tell request has no working directory. Open Tell for a directory first.');
        }

        return new TellOptions(
            prompt: $this->prompt,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
            reasoningEffort: $this->reasoningEffort,
            dsn: $this->dsn,
            session: $this->session,
            branch: $this->branch,
            directory: $this->directory,
            tools: $this->tools,
            answers: $this->answers,
            maxSteps: $this->maxSteps,
            output: 'text',
            transient: $this->mode === TellExecutionMode::Transient,
            connectionExplicit: $this->connectionExplicit,
            modelExplicit: $this->modelExplicit,
            reasoningEffortExplicit: $this->reasoningEffortExplicit,
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    public function maxRetries(int $maxRetries): self {
        return $this->withPolicyOverrides(['maxRetries' => $maxRetries]);
    }

    public function timeoutMs(int $timeoutMs): self {
        return $this->withPolicyOverrides(['timeoutMs' => $timeoutMs]);
    }

    public function maxOutputChars(int $maxOutputChars): self {
        return $this->withPolicyOverrides(['maxOutputChars' => $maxOutputChars]);
    }

    public function maxToolOutputChars(int $maxToolOutputChars): self {
        return $this->withPolicyOverrides(['maxToolOutputChars' => $maxToolOutputChars]);
    }

    public function maxToolCalls(int $maxToolCalls): self {
        return $this->withPolicyOverrides(['maxToolCalls' => $maxToolCalls]);
    }

    /** Zero turns tool-output spilling off for this request. */
    public function maxSpillBytes(int $maxSpillBytes): self {
        return $this->withPolicyOverrides(['maxSpillBytes' => $maxSpillBytes]);
    }

    /** How much of the spilled result its stub may preview. */
    public function maxStubBytes(int $maxStubBytes): self {
        return $this->withPolicyOverrides(['maxStubBytes' => $maxStubBytes]);
    }

    public function withPolicy(TellExecutionPolicy $policy): self {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
            reasoningEffort: $this->reasoningEffort,
            dsn: $this->dsn,
            session: $this->session,
            branch: $this->branch,
            tools: $this->tools,
            answers: $this->answers,
            maxSteps: $this->maxSteps,
            mode: $this->mode,
            listeners: $this->listeners,
            connectionExplicit: $this->connectionExplicit,
            modelExplicit: $this->modelExplicit,
            reasoningEffortExplicit: $this->reasoningEffortExplicit,
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $policy,
        );
    }

    private function withMode(TellExecutionMode $mode, ?string $session): self {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
            reasoningEffort: $this->reasoningEffort,
            dsn: $this->dsn,
            session: $session,
            branch: $this->branch,
            tools: $this->tools,
            answers: $this->answers,
            maxSteps: $this->maxSteps,
            mode: $mode,
            listeners: $this->listeners,
            connectionExplicit: $this->connectionExplicit,
            modelExplicit: $this->modelExplicit,
            reasoningEffortExplicit: $this->reasoningEffortExplicit,
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    /** @param array<string, int> $overrides */
    private function withPolicyOverrides(array $overrides): self {
        $resolved = [...$this->policyOverrides, ...$overrides];
        $policy = TellExecutionPolicy::resolve([], $resolved);

        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
            reasoningEffort: $this->reasoningEffort,
            dsn: $this->dsn,
            session: $this->session,
            branch: $this->branch,
            tools: $this->tools,
            answers: $this->answers,
            maxSteps: $this->maxSteps,
            mode: $this->mode,
            listeners: $this->listeners,
            connectionExplicit: $this->connectionExplicit,
            modelExplicit: $this->modelExplicit,
            reasoningEffortExplicit: $this->reasoningEffortExplicit,
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $resolved,
            policy: $policy,
        );
    }
}
