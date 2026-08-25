<?php

declare(strict_types=1);

namespace Cognesy\Tell;

use Cognesy\Tell\Capability\AskUser\TellAnswerQueue;
use Cognesy\Tell\Runtime\TellOptions;
use Cognesy\Tell\Runtime\TellExecutionPolicy;
use Closure;
use InvalidArgumentException;

final readonly class TellRequest
{
    /**
     * @param list<string> $tools
     * @param list<Closure(TellEvent): void> $listeners
     */
    public function __construct(
        public string $prompt,
        public string $directory = '',
        public string $agent = 'default',
        public string $connection = 'openai',
        public string $model = '',
        public string $dsn = '',
        public ?string $session = null,
        public ?string $branch = null,
        public array $tools = [],
        public TellAnswerQueue $answers = new TellAnswerQueue,
        public int $maxSteps = 10,
        public TellExecutionMode $mode = TellExecutionMode::Stateless,
        private array $listeners = [],
        public bool $connectionExplicit = false,
        public bool $modelExplicit = false,
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
        if ($directory !== '' && ! is_dir($directory)) {
            throw new InvalidArgumentException("Working directory does not exist: {$directory}");
        }
    }

    public static function prompt(string $prompt): self
    {
        return new self(prompt: $prompt);
    }

    public static function fromOptions(TellOptions $options): self
    {
        return new self(
            prompt: $options->prompt,
            directory: $options->directory,
            agent: $options->agent,
            connection: $options->connection,
            model: $options->model,
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
            toolsExplicit: $options->toolsExplicit,
            policyOverrides: $options->policyOverrides,
            policy: $options->policy ?? TellExecutionPolicy::resolve([], $options->policyOverrides),
        );
    }

    public function withDirectory(string $directory): self
    {
        return new self(
            prompt: $this->prompt,
            directory: $directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
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
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy ?? TellExecutionPolicy::resolve([], $this->policyOverrides),
        );
    }

    public function agent(string $agent): self
    {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $agent,
            connection: $this->connection,
            model: $this->model,
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
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    public function connection(string $connection): self
    {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $connection,
            model: $this->model,
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
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    public function model(string $model): self
    {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $model,
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
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    /** @param list<string> $tools */
    public function tools(array $tools): self
    {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
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
    public function withAnswers(TellAnswerQueue $answers): self
    {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
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
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    public function maxSteps(int $maxSteps): self
    {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
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
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    public function conversation(?string $session): self
    {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
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
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    public function branch(?string $branch): self
    {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
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
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    /** @param array<string, mixed> $values */
    public function withBranchConfig(array $values): self
    {
        $connection = $this->dsn === '' && ! $this->connectionExplicit && isset($values['connection']) && is_string($values['connection'])
            ? $values['connection']
            : $this->connection;
        $model = $this->dsn === '' && ! $this->modelExplicit && isset($values['model']) && is_string($values['model'])
            ? $values['model']
            : $this->model;
        $tools = ! $this->toolsExplicit && isset($values['tools']) && is_array($values['tools'])
            ? array_values(array_filter($values['tools'], static fn (mixed $tool): bool => is_string($tool)))
            : $this->tools;

        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $connection,
            model: $model,
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
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: TellExecutionPolicy::resolve($values, $this->policyOverrides),
        );
    }

    /** @param callable(TellEvent): void $listener */
    public function onEvent(callable $listener): self
    {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
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
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    /** @return list<Closure(TellEvent): void> */
    public function listeners(): array
    {
        return $this->listeners;
    }

    public function transient(): self
    {
        return $this->withMode(TellExecutionMode::Transient, $this->session);
    }

    public function durable(?string $session = null): self
    {
        return $this->withMode(TellExecutionMode::Durable, $session ?? $this->session);
    }

    public function toOptions(): TellOptions
    {
        if ($this->directory === '') {
            throw new InvalidArgumentException('Tell request has no working directory. Open Tell for a directory first.');
        }

        return new TellOptions(
            prompt: $this->prompt,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
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
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    public function maxRetries(int $maxRetries): self
    {
        return $this->withPolicyOverrides(['maxRetries' => $maxRetries]);
    }

    public function timeoutMs(int $timeoutMs): self
    {
        return $this->withPolicyOverrides(['timeoutMs' => $timeoutMs]);
    }

    public function maxOutputChars(int $maxOutputChars): self
    {
        return $this->withPolicyOverrides(['maxOutputChars' => $maxOutputChars]);
    }

    public function maxToolOutputChars(int $maxToolOutputChars): self
    {
        return $this->withPolicyOverrides(['maxToolOutputChars' => $maxToolOutputChars]);
    }

    public function maxToolCalls(int $maxToolCalls): self
    {
        return $this->withPolicyOverrides(['maxToolCalls' => $maxToolCalls]);
    }

    public function withPolicy(TellExecutionPolicy $policy): self
    {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
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
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $policy,
        );
    }

    private function withMode(TellExecutionMode $mode, ?string $session): self
    {
        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
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
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    /** @param array<string, int> $overrides */
    private function withPolicyOverrides(array $overrides): self
    {
        $resolved = [...$this->policyOverrides, ...$overrides];
        $policy = TellExecutionPolicy::resolve([], $resolved);

        return new self(
            prompt: $this->prompt,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
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
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $resolved,
            policy: $policy,
        );
    }
}
