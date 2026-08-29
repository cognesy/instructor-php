<?php

declare(strict_types=1);

namespace Cognesy\Tell\Command;

use Cognesy\Tell\Observability\TellEventNormalizer;
use Cognesy\Tell\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Operational\OperationalPlane;
use Cognesy\Tell\Operational\PlaneOperation;
use Cognesy\Tell\Render\StructuredOutput;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Runtime\TellExecutionPolicy;
use Cognesy\Tell\Runtime\TellOptions;
use Cognesy\Tell\Runtime\TellSignalCancellationSource;
use Cognesy\Tell\Runtime\TellToolDispatcher;
use InvalidArgumentException;
use JsonException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/** Execute one registered tool directly, without starting an agent turn. */
final class ToolCommand extends Command implements CanDescribeOperationalPlane
{
    private const int MAX_INPUT_BYTES = 1_048_576;

    public function __construct(private readonly TellAgentFactory $agents)
    {
        parent::__construct('tool');
    }

    #[Override]
    protected function configure(): void
    {
        $this->setDescription('Invoke one resolved Tell tool without model inference')
            ->setHelp(<<<'HELP'
Invoke exactly one tool from the same resolved registry an agent would receive.
Arguments are a JSON object supplied as a positional value, --input-file, or --stdin.
No agent state, session, trace, or workspace ref is written.

Examples:
  tell tool read_file '{"path":"README.md"}' --output=json
  tell tool apply_patch --input-file patch.json --output=events
  printf '%s' '{"command":"git status --short"}' | tell tool shell --stdin
HELP)
            ->addArgument('name', InputArgument::REQUIRED, 'Registered tool name')
            ->addArgument('input', InputArgument::OPTIONAL, 'Small JSON object of tool arguments')
            ->addOption('input-file', null, InputOption::VALUE_REQUIRED, 'Read a JSON object from a UTF-8 file')
            ->addOption('stdin', null, InputOption::VALUE_NONE, 'Read a JSON object from standard input')
            ->addOption('agent', 'a', InputOption::VALUE_REQUIRED, 'Agent definition name', 'default')
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'LLM connection preset name', 'openai')
            ->addOption('model', 'm', InputOption::VALUE_REQUIRED, 'Model override', '')
            ->addOption('dsn', 'd', InputOption::VALUE_REQUIRED, 'Inline LLM DSN', '')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Use one workspace branch configuration')
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Tool working directory', '')
            ->addOption('tools', null, InputOption::VALUE_REQUIRED, 'Comma-separated tool allow-list', '')
            ->addOption('max-steps', null, InputOption::VALUE_REQUIRED, 'Maximum agent steps', '10')
            ->addOption('max-retries', null, InputOption::VALUE_REQUIRED, 'Maximum provider retries', '0')
            ->addOption('timeout-ms', null, InputOption::VALUE_REQUIRED, 'Wall-time budget in milliseconds', '30000')
            ->addOption('max-output-chars', null, InputOption::VALUE_REQUIRED, 'Maximum total model-output bytes', '200000')
            ->addOption('max-tool-output-chars', null, InputOption::VALUE_REQUIRED, 'Maximum bytes retained from this tool result', '40000')
            ->addOption('max-tool-calls', null, InputOption::VALUE_REQUIRED, 'Maximum tool calls', '100')
            ->addOption('max-spill-chars', null, InputOption::VALUE_REQUIRED, 'Maximum bytes spilled to a blob from this tool result (0 disables spilling)', '1000000')
            ->addOption('max-stub-chars', null, InputOption::VALUE_REQUIRED, 'Maximum bytes one spill stub may spend previewing the result it replaces', '2000')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output: toon, text, json, or events', 'toon')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Shortcut for --output=json');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $options = $this->options($input);
            $name = (string) $input->getArgument('name');
            $arguments = $this->arguments($input);
            $cancellation = new TellSignalCancellationSource;
            $cancellation->install();
            $result = (new TellToolDispatcher($this->agents, $cancellation))->dispatch($options, $name, $arguments);
            $this->render($output, $options->output, $name, $result);

            return $result['success'] === true ? Command::SUCCESS : Command::FAILURE;
        } catch (InvalidArgumentException $error) {
            $this->error($output, $this->output($input), $error->getMessage());

            return Command::INVALID;
        } catch (Throwable) {
            $this->error($output, $this->output($input), 'Tool invocation could not be completed.');

            return Command::FAILURE;
        }
    }

    #[Override]
    public function planeOperation(): PlaneOperation
    {
        return new PlaneOperation(
            plane: OperationalPlane::Data,
            command: 'tool NAME JSON',
            responsibility: 'Execute one resolved tool operation directly and return its bounded structured result.',
            ownedState: 'One ephemeral tool invocation; no AgentState, session, trace, or workspace ref.',
            input: 'Tool name, a strict JSON argument object, resolved tool policy, and optional branch-local configuration.',
            output: 'Stable direct invocation result or payload-free normalized events.',
            authority: 'Exactly one enabled tool under its existing path, sandbox, network, timeout, and output policy; never inference or conversation publication.',
            degradedBehavior: 'Invalid arguments are usage errors; unavailable, policy-rejected, cancelled, and runtime failures return a bounded non-zero result.',
        );
    }

    private function options(InputInterface $input): TellOptions
    {
        $directory = (string) $input->getOption('dir');
        $cwd = getcwd();
        $directory = $directory !== '' ? $directory : (is_string($cwd) ? $cwd : '.');

        return new TellOptions(
            prompt: 'Direct tool invocation.',
            agent: (string) $input->getOption('agent'),
            connection: (string) $input->getOption('connection'),
            model: (string) $input->getOption('model'),
            dsn: (string) $input->getOption('dsn'),
            branch: $this->nullable((string) $input->getOption('branch')),
            directory: $directory,
            tools: $this->tools((string) $input->getOption('tools')),
            maxSteps: (int) $input->getOption('max-steps'),
            output: $this->output($input),
            connectionExplicit: $input->hasParameterOption(['--connection', '-c'], true),
            modelExplicit: $input->hasParameterOption(['--model', '-m'], true),
            toolsExplicit: $input->hasParameterOption('--tools', true),
            policyOverrides: TellExecutionPolicy::overridesFromInput($input),
        );
    }

    /** @return array<string, mixed> */
    private function arguments(InputInterface $input): array
    {
        $inline = $input->getArgument('input');
        $file = $input->getOption('input-file');
        $stdin = (bool) $input->getOption('stdin');
        $sources = (is_string($inline) && $inline !== '' ? 1 : 0)
            + (is_string($file) && $file !== '' ? 1 : 0)
            + ($stdin ? 1 : 0);
        if ($sources !== 1) {
            throw new InvalidArgumentException('Provide exactly one JSON argument source: positional input, --input-file, or --stdin.');
        }
        $raw = match (true) {
            is_string($inline) && $inline !== '' => $inline,
            is_string($file) && $file !== '' => $this->readFile($file),
            default => $this->readStdin(),
        };
        if (preg_match('//u', $raw) !== 1) {
            throw new InvalidArgumentException('Tool arguments must be valid UTF-8 JSON.');
        }
        try {
            $arguments = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Tool arguments must be a valid JSON object.');
        }
        if (! is_array($arguments) || array_is_list($arguments)) {
            throw new InvalidArgumentException('Tool arguments must be a JSON object.');
        }

        return $arguments;
    }

    private function readFile(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("Cannot read tool argument file: {$path}");
        }
        $size = filesize($path);
        if ($size === false || $size > self::MAX_INPUT_BYTES) {
            throw new InvalidArgumentException('--input-file exceeds the 1048576-byte input limit.');
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new InvalidArgumentException("Cannot read tool argument file: {$path}");
        }

        return $content;
    }

    private function readStdin(): string
    {
        $content = stream_get_contents(STDIN, self::MAX_INPUT_BYTES + 1);
        if ($content === false || strlen($content) > self::MAX_INPUT_BYTES) {
            throw new InvalidArgumentException('--stdin exceeds the 1048576-byte input limit.');
        }

        return $content;
    }

    /** @param array<string, mixed> $result */
    private function render(OutputInterface $output, string $mode, string $name, array $result): void
    {
        if ($mode === 'events') {
            $events = new TellEventNormalizer;
            foreach ([
                $events->direct('tool.started', ['tool' => $name, 'effect' => (string) $result['effect']]),
                $events->direct('tool.completed', ['tool' => $name, 'success' => $result['success'] === true, 'truncated' => $result['truncated'] === true]),
                $events->terminal($result['success'] === true ? 'completed' : 'failed', ['tool' => $name]),
            ] as $event) {
                $output->writeln(json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            }

            return;
        }
        (new StructuredOutput($output))->write($result, json: $mode === 'json');
    }

    private function error(OutputInterface $output, string $mode, string $message): void
    {
        if ($mode === 'events') {
            $output->writeln(json_encode(
                (new TellEventNormalizer)->terminal('failed', ['errorCode' => 'invalid_input']),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ));

            return;
        }
        (new StructuredOutput($output))->write([
            'success' => false,
            'error' => ['code' => 'invalid_input', 'message' => $message],
            'execution' => ['mode' => 'direct', 'inference' => false, 'durable' => false],
        ], json: $mode === 'json');
    }

    private function output(InputInterface $input): string
    {
        return (bool) $input->getOption('json') ? 'json' : (string) $input->getOption('output');
    }

    /** @return list<string> */
    private function tools(string $tools): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $tools)), static fn (string $name): bool => $name !== '')));
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
