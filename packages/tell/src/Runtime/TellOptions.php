<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Tell\Capability\AskUser\TellAnswerQueue;
use Cognesy\Tell\TellReasoningEffort;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class TellOptions
{
    /** @param list<string> $tools */
    public function __construct(
        public string $prompt,
        public string $agent = 'default',
        public string $connection = 'openai',
        public string $model = '',
        public ?TellReasoningEffort $reasoningEffort = null,
        public string $dsn = '',
        public ?string $session = null,
        public ?string $branch = null,
        public string $directory = '',
        public array $tools = [],
        public TellAnswerQueue $answers = new TellAnswerQueue,
        public int $maxSteps = 10,
        public string $output = 'toon',
        public bool $verbose = false,
        public bool $quiet = false,
        public bool $transient = false,
        public bool $debug = false,
        public bool $debugFull = false,
        public bool $outputExplicit = false,
        public bool $connectionExplicit = false,
        public bool $modelExplicit = false,
        public bool $reasoningEffortExplicit = false,
        public bool $toolsExplicit = false,
        /** @var array<string, int> */
        public array $policyOverrides = [],
        public ?TellExecutionPolicy $policy = null,
    ) {
        if ($this->prompt === '') {
            throw new InvalidArgumentException('Prompt must not be empty.');
        }
        if ($this->agent === '') {
            throw new InvalidArgumentException('Agent name must not be empty.');
        }
        if (! is_dir($this->directory)) {
            throw new InvalidArgumentException("Working directory does not exist: {$this->directory}");
        }
        if ($this->maxSteps < 1) {
            throw new InvalidArgumentException('--max-steps must be at least 1.');
        }
        if (! in_array($this->output, ['toon', 'text', 'human', 'json', 'events'], true)) {
            throw new InvalidArgumentException('--output must be one of: toon, text, human, json, events.');
        }
        if ($this->verbose && $this->quiet) {
            throw new InvalidArgumentException('--verbose and --quiet cannot be used together.');
        }
        if ($this->debug && $this->quiet) {
            throw new InvalidArgumentException('--debug and --quiet cannot be used together.');
        }
    }

    public static function fromInput(InputInterface $input, OutputInterface $output): self
    {
        $directory = (string) $input->getOption('dir');
        $cwd = getcwd();
        $resolvedDirectory = match (true) {
            $directory !== '' => $directory,
            is_string($cwd) => $cwd,
            default => '.',
        };

        return new self(
            prompt: (string) $input->getArgument('prompt'),
            agent: (string) $input->getOption('agent'),
            connection: (string) $input->getOption('connection'),
            model: (string) $input->getOption('model'),
            reasoningEffort: self::reasoningEffort($input->getOption('reasoning-effort')),
            dsn: (string) $input->getOption('dsn'),
            session: self::nullableString($input->getOption('session')),
            branch: self::nullableString($input->getOption('branch')),
            directory: $resolvedDirectory,
            tools: self::parseTools((string) $input->getOption('tools')),
            answers: TellAnswerQueue::fromInput($input),
            maxSteps: (int) $input->getOption('max-steps'),
            output: (string) $input->getOption('output'),
            verbose: $output->isVerbose(),
            quiet: $output->isQuiet(),
            transient: (bool) $input->getOption('transient'),
            // -vv asks for the same account of the turn that --debug names
            // explicitly, and -vvv is the conventional place to stop abridging.
            debug: (bool) $input->getOption('debug') || $output->isVeryVerbose(),
            debugFull: $output->isDebug(),
            outputExplicit: $input->hasParameterOption(['--output', '-o'], true),
            connectionExplicit: $input->hasParameterOption(['--connection', '-c'], true),
            modelExplicit: $input->hasParameterOption(['--model', '-m'], true),
            reasoningEffortExplicit: $input->hasParameterOption('--reasoning-effort', true),
            toolsExplicit: $input->hasParameterOption('--tools', true),
            policyOverrides: TellExecutionPolicy::overridesFromInput($input),
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
        $reasoningEffort = ! $this->reasoningEffortExplicit && isset($values['reasoningEffort']) && is_string($values['reasoningEffort'])
            ? TellReasoningEffort::parse($values['reasoningEffort'])
            : $this->reasoningEffort;
        $tools = ! $this->toolsExplicit && isset($values['tools']) && is_array($values['tools'])
            ? array_values(array_filter($values['tools'], static fn (mixed $tool): bool => is_string($tool)))
            : $this->tools;
        $output = ! $this->outputExplicit && isset($values['output']) && is_string($values['output'])
            ? $values['output']
            : $this->output;

        return new self(
            prompt: $this->prompt,
            agent: $this->agent,
            connection: $connection,
            model: $model,
            reasoningEffort: $reasoningEffort,
            dsn: $this->dsn,
            session: $this->session,
            branch: $this->branch,
            directory: $this->directory,
            tools: $tools,
            answers: $this->answers,
            maxSteps: $this->maxSteps,
            output: $output,
            verbose: $this->verbose,
            quiet: $this->quiet,
            transient: $this->transient,
            debug: $this->debug,
            debugFull: $this->debugFull,
            outputExplicit: $this->outputExplicit,
            connectionExplicit: $this->connectionExplicit,
            modelExplicit: $this->modelExplicit,
            reasoningEffortExplicit: $this->reasoningEffortExplicit,
            toolsExplicit: $this->toolsExplicit,
            policyOverrides: $this->policyOverrides,
            policy: TellExecutionPolicy::resolve($values, $this->policyOverrides),
        );
    }

    /** @return list<string> */
    private static function parseTools(string $tools): array
    {
        if (trim($tools) === '') {
            return [];
        }
        $names = array_map('trim', explode(',', $tools));

        return array_values(array_unique(array_filter($names, static fn (string $name): bool => $name !== '')));
    }

    private static function nullableString(mixed $value): ?string
    {
        return match (true) {
            is_string($value) && $value !== '' => $value,
            default => null,
        };
    }

    /** @return 'branch'|'invocation'|null */
    public function reasoningEffortSource(): ?string
    {
        return match (true) {
            $this->reasoningEffort === null => null,
            $this->reasoningEffortExplicit => 'invocation',
            default => 'branch',
        };
    }

    private static function reasoningEffort(mixed $value): ?TellReasoningEffort
    {
        return match (true) {
            is_string($value) && trim($value) !== '' => TellReasoningEffort::parse($value),
            default => null,
        };
    }
}
