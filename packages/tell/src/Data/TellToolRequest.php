<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

use Cognesy\Tell\Configuration\TellExecutionPolicy;
use Cognesy\Tell\Console\TellOptions;
use InvalidArgumentException;

/** One direct Tell tool invocation, without model inference or conversation publication. */
final readonly class TellToolRequest
{
    /** @param array<string, mixed> $arguments @param list<string> $tools */
    private function __construct(
        public string $name,
        public array $arguments,
        public string $directory = '',
        public string $agent = 'default',
        public string $connection = 'openai',
        public string $model = '',
        public string $dsn = '',
        public ?string $branch = null,
        /** @var list<string> */
        public array $tools = [],
        public int $maxSteps = 10,
        public bool $connectionExplicit = false,
        public bool $modelExplicit = false,
        public bool $toolsExplicit = false,
        /** @var array<string, int> */
        public array $policyOverrides = [],
        public ?TellExecutionPolicy $policy = null,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Tell tool name must not be empty.');
        }
        if ($agent === '' || $maxSteps < 1) {
            throw new InvalidArgumentException('Tell direct tool request has invalid agent or maximum steps.');
        }
    }

    /** @param array<string, mixed> $arguments */
    public static function invoke(string $name, array $arguments): self {
        return new self($name, $arguments);
    }

    /** @param array<string, mixed> $arguments */
    public static function fromOptions(TellOptions $options, string $name, array $arguments): self {
        return new self(
            name: $name,
            arguments: $arguments,
            directory: $options->directory,
            agent: $options->agent,
            connection: $options->connection,
            model: $options->model,
            dsn: $options->dsn,
            branch: $options->branch,
            tools: $options->tools,
            maxSteps: $options->maxSteps,
            connectionExplicit: $options->connectionExplicit,
            modelExplicit: $options->modelExplicit,
            toolsExplicit: $options->toolsExplicit,
            policyOverrides: $options->policyOverrides,
            policy: $options->policy ?? TellExecutionPolicy::resolve([], $options->policyOverrides),
        );
    }

    public function branch(?string $branch): self {
        return new self(
            name: $this->name,
            arguments: $this->arguments,
            directory: $this->directory,
            agent: $this->agent,
            connection: $this->connection,
            model: $this->model,
            dsn: $this->dsn,
            branch: $branch,
            tools: $this->tools,
            maxSteps: $this->maxSteps,
            connectionExplicit: $this->connectionExplicit,
            modelExplicit: $this->modelExplicit,
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $this->policy,
        );
    }

    /** @param list<string> $tools */
    public function tools(array $tools): self {
        return $this->copy(tools: $tools);
    }

    public function connection(string $connection): self {
        return $this->copy(connection: $connection);
    }

    public function model(string $model): self {
        return $this->copy(model: $model);
    }

    public function policy(TellExecutionPolicy $policy): self {
        return $this->copy(policy: $policy);
    }

    /** @param list<string>|null $tools */
    private function copy(
        ?array $tools = null,
        ?string $connection = null,
        ?string $model = null,
        ?TellExecutionPolicy $policy = null,
    ): self {
        return new self(
            name: $this->name,
            arguments: $this->arguments,
            directory: $this->directory,
            agent: $this->agent,
            connection: $connection ?? $this->connection,
            model: $model ?? $this->model,
            dsn: $this->dsn,
            branch: $this->branch,
            tools: $tools ?? $this->tools,
            maxSteps: $this->maxSteps,
            connectionExplicit: $connection !== null || $this->connectionExplicit,
            modelExplicit: $model !== null || $this->modelExplicit,
            toolsExplicit: $tools !== null || $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: $policy ?? $this->policy,
        );
    }
}
