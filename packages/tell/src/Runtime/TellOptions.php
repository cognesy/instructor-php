<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

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
        public string $dsn = '',
        public ?string $session = null,
        public string $directory = '',
        public array $tools = [],
        public int $maxSteps = 10,
        public string $output = 'toon',
        public bool $verbose = false,
        public bool $quiet = false,
        public bool $transient = false,
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
        if (! in_array($this->output, ['toon', 'text', 'json', 'events'], true)) {
            throw new InvalidArgumentException('--output must be one of: toon, text, json, events.');
        }
        if ($this->verbose && $this->quiet) {
            throw new InvalidArgumentException('--verbose and --quiet cannot be used together.');
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
            dsn: (string) $input->getOption('dsn'),
            session: self::nullableString($input->getOption('session')),
            directory: $resolvedDirectory,
            tools: self::parseTools((string) $input->getOption('tools')),
            maxSteps: (int) $input->getOption('max-steps'),
            output: (string) $input->getOption('output'),
            verbose: $output->isVerbose(),
            quiet: $output->isQuiet(),
            transient: (bool) $input->getOption('transient'),
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
}
