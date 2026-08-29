<?php

declare(strict_types=1);

namespace Cognesy\Tell;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Tell\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Operational\OperationalPlane;
use Cognesy\Tell\Operational\PlaneOperation;
use Cognesy\Tell\Render\EventsRenderer;
use Cognesy\Tell\Render\HumanRenderer;
use Cognesy\Tell\Render\JsonRenderer;
use Cognesy\Tell\Render\OutputRenderer;
use Cognesy\Tell\Render\StepTrace;
use Cognesy\Tell\Render\StructuredOutput;
use Cognesy\Tell\Render\TextRenderer;
use Cognesy\Tell\Render\ToonRenderer;
use Cognesy\Tell\Observability\TellEventNormalizer;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Runtime\TellOptions;
use Cognesy\Tell\Runtime\TellRuntime;
use Cognesy\Tell\Runtime\TellSignalCancellationSource;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\BranchConfigStore;
use Cognesy\Tell\Workspace\BranchResolver;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class TellCommand extends Command implements CanDescribeOperationalPlane
{
    private readonly TellAgentFactory $agents;

    public function __construct(?TellAgentFactory $agents = null)
    {
        $this->agents = $agents ?? TellAgentFactory::installed();
        parent::__construct('tell');
    }

    #[Override]
    protected function configure(): void
    {
        $this->setDescription('Run one non-interactive agent turn')
            ->setHelp(<<<'HELP'
Run an agent turn, or omit the prompt to discover available agents and next actions.

Examples:
  tell "summarize this repository"
  tell "continue the review" --session review-1
  tell --transient "test a direction without recording it"
  tell --output=text "write a commit message"
  tell --output=human "explain this design"
  tell --debug "fix the failing test"
HELP)
            ->addArgument('prompt', InputArgument::OPTIONAL, 'Prompt')
            ->addOption('agent', 'a', InputOption::VALUE_REQUIRED, 'Agent definition name', 'default')
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'LLM connection preset name', 'openai')
            ->addOption('model', 'm', InputOption::VALUE_REQUIRED, 'Model override', '')
            ->addOption('reasoning-effort', null, InputOption::VALUE_REQUIRED, 'Reasoning effort: low, medium, or high')
            ->addOption('dsn', 'd', InputOption::VALUE_REQUIRED, 'Inline LLM DSN', '')
            ->addOption('session', 's', InputOption::VALUE_REQUIRED, 'Persist or continue a named session')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Use one workspace branch for this invocation')
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Tool working directory', '')
            ->addOption('tools', null, InputOption::VALUE_REQUIRED, 'Comma-separated tool allow-list', '')
            ->addOption('answer', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Pre-supplied non-interactive answer; repeat for each question')
            ->addOption('answers-file', null, InputOption::VALUE_REQUIRED, 'UTF-8 JSON answer array for multiple questions')
            ->addOption('answers-stdin', null, InputOption::VALUE_NONE, 'Read a UTF-8 JSON answer array from standard input')
            ->addOption('max-steps', null, InputOption::VALUE_REQUIRED, 'Maximum agent steps', '10')
            ->addOption('max-retries', null, InputOption::VALUE_REQUIRED, 'Maximum provider retries', '0')
            ->addOption('timeout-ms', null, InputOption::VALUE_REQUIRED, 'Wall-time budget in milliseconds', '30000')
            ->addOption('max-output-chars', null, InputOption::VALUE_REQUIRED, 'Maximum total model-output bytes', '200000')
            ->addOption('max-tool-output-chars', null, InputOption::VALUE_REQUIRED, 'Maximum bytes retained from one tool result', '40000')
            ->addOption('max-tool-calls', null, InputOption::VALUE_REQUIRED, 'Maximum tool calls', '100')
            ->addOption('transient', null, InputOption::VALUE_NONE, 'Run without publishing workspace or session state')
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Trace steps, tool calls, and tool results on stderr')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output: toon, text, human, json, or events', 'toon');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stderr = match (true) {
            $output instanceof ConsoleOutputInterface => $output->getErrorOutput(),
            default => $output,
        };
        try {
            $prompt = $input->getArgument('prompt');
            if (! is_string($prompt) || $prompt === '') {
                return $this->showHome($input, $output);
            }
            $options = $this->withBranchSettings(TellOptions::fromInput($input, $output));
            $renderer = $this->renderer($options, $output, $stderr);
            // The trace is its own stderr channel rather than an output mode,
            // so it composes with whichever format stdout was asked for.
            $trace = $options->debug ? new StepTrace($stderr, $options->debugFull) : null;
            $cancellation = new TellSignalCancellationSource;
            $signalsEnabled = $cancellation->install();
            $result = (new TellRuntime($this->factory(), $cancellation))->run(
                TellRequest::fromOptions($options),
                static function (AgentLoop $loop, TellRequest $request, ?string $selectedBranch = null) use ($renderer, $trace): void {
                    $renderer->attach($loop, new TellEventNormalizer($selectedBranch ?? $request->branch, $request->session));
                    $trace?->attach($loop);
                },
            );
            $branch = match ($result->branch()) {
                null => null,
                default => ['name' => $result->branch(), 'source' => $result->branchSource() ?? 'current'],
            };
            $warnings = $result->warnings();
            if ($options->answers->remaining() > 0) {
                $warnings[] = "Unused non-interactive answers: {$options->answers->remaining()}.";
            }
            $diagnostics = array_map(
                static fn (\Cognesy\Tell\Diagnostics\TellDiagnostic $diagnostic): array => $diagnostic->toArray(),
                $result->diagnostics(),
            );
            $renderer->finish($result->state(), $warnings, $result->executionMode(), $branch, $diagnostics);
            if (! $signalsEnabled && $output->isVerbose()) {
                $stderr->writeln('[tell] SIGINT cancellation is unavailable: pcntl signal support was not detected.');
            }

            return $this->exitCode($result->state());
        } catch (InvalidArgumentException $error) {
            $this->writeError($input, $output, $error->getMessage(), true);

            return Command::INVALID;
        } catch (Throwable $error) {
            $this->writeError($input, $output, $error->getMessage(), false);

            return Command::FAILURE;
        }
    }

    #[Override]
    public function planeOperation(): PlaneOperation
    {
        return new PlaneOperation(
            plane: OperationalPlane::Data,
            command: 'tell "<prompt>"',
            responsibility: 'Execute one already-selected agent turn and emit bounded result evidence.',
            ownedState: 'AgentState plus one append-only trace; initialized workspaces publish immutable arena turns unless --transient, and AgentSession is used only when --session is explicit and durable.',
            input: 'Prompt plus an immutable resolved AgentDefinition and AgentProfile.',
            output: 'Terminal AgentState projection or typed events, explicit durable/transient/stateless mode, an execution trace, and optional updated AgentSession.',
            authority: 'Inference, resolved tools, its trace target, and optional write access to one named session; --transient is read-only for conversation/session state.',
            degradedBehavior: 'Fails before inference when control resolution fails; trace-write failure does not fail the turn; stateless turns need no session storage.',
        );
    }

    /**
     * Branch configuration can decide the output format, so it has to be read
     * before the renderer is chosen rather than inside the runtime where the
     * rest of it is applied. Both passes are the same explicit-wins merge over
     * the same values, so resolving here changes nothing the runtime does.
     */
    private function withBranchSettings(TellOptions $options): TellOptions
    {
        // An explicit --output already outranks anything a branch could say, so
        // there is nothing worth a workspace lookup to discover.
        if ($options->outputExplicit || $options->session !== null) {
            return $options;
        }
        $workspace = $this->factory()->workspace()->discover($options->directory);
        if ($workspace === null) {
            return $options;
        }
        $branch = (new BranchResolver(new ArenaStore($workspace)))->resolve($options->branch);

        return $options->withBranchConfig((new BranchConfigStore($workspace))->runtimeValues($branch->branch));
    }

    private function renderer(
        TellOptions $options,
        OutputInterface $stdout,
        OutputInterface $stderr,
    ): OutputRenderer {
        return match ($options->output) {
            'json' => new JsonRenderer($stdout),
            'events' => new EventsRenderer($stdout),
            'text' => new TextRenderer($stdout, $stderr, $options->verbose, $options->quiet),
            'human' => new HumanRenderer($stdout, $stderr, $options->verbose, $options->quiet),
            default => new ToonRenderer($stdout, $stderr, $options->verbose, $options->quiet),
        };
    }

    private function exitCode(AgentState $state): int
    {
        return match (true) {
            $state->status() === ExecutionStatus::Failed => Command::FAILURE,
            ! $state->hasFinalResponse() => Command::FAILURE,
            $state->status() === ExecutionStatus::Completed => Command::SUCCESS,
            default => Command::SUCCESS,
        };
    }

    private function showHome(InputInterface $input, OutputInterface $output): int
    {
        $mode = (string) $input->getOption('output');
        if (! in_array($mode, ['toon', 'json'], true)) {
            throw new InvalidArgumentException('Without a prompt, --output must be toon or json.');
        }
        $directory = (string) $input->getOption('dir');
        $cwd = getcwd();
        $project = match (true) {
            $directory !== '' => $directory,
            is_string($cwd) => $cwd,
            default => '.',
        };
        if (! is_dir($project)) {
            throw new InvalidArgumentException("Working directory does not exist: {$project}");
        }
        $workspace = $this->factory()->workspace()->discover($project);
        $registry = $this->factory()->definitions($project);
        $agents = [];
        foreach ($registry->all() as $definition) {
            $agents[] = [
                'name' => $definition->name,
                'label' => $definition->label(),
                'description' => $definition->description,
            ];
        }
        usort($agents, static fn (array $left, array $right): int => $left['name'] <=> $right['name']);
        (new StructuredOutput($output))->write([
            'bin' => $this->binary(),
            'description' => 'Run and inspect Instructor agents in the current workspace.',
            'directory' => $project,
            'workspace' => $workspace?->toArray(),
            'storage' => $this->factory()->paths()->toArray(),
            'observability' => $this->factory()->config()->toArray(),
            'agentCount' => count($agents),
            'agents' => $agents,
            'discoveryErrors' => count($registry->errors()),
            'help' => [
                'Run `tell "<prompt>"` to start a stateless turn.',
                'Run `tell agents` to inspect all definitions.',
                'Run `tell auth status` to inspect credential availability and provenance.',
                'Run `tell planes` to inspect operational ownership and authority.',
                'Run `tell sessions` to inspect persisted sessions.',
                'Run `tell init` to initialize durable project state.',
                'Trace roots are reported as storage.executionTraces and storage.sessionTraces.',
                'Run `tell --help` for all turn options.',
            ],
        ], json: $mode === 'json');

        return Command::SUCCESS;
    }

    private function binary(): string
    {
        $binary = $_SERVER['argv'][0] ?? 'tell';
        if (! is_string($binary) || $binary === '') {
            return 'tell';
        }
        return $binary;
    }

    private function factory(): TellAgentFactory
    {
        return $this->agents;
    }

    private function writeError(
        InputInterface $input,
        OutputInterface $output,
        string $message,
        bool $usage,
    ): void {
        $payload = ['error' => $message];
        if ((bool) $input->getOption('transient')) {
            $payload['execution'] = ['mode' => 'transient', 'durable' => false];
        }
        if ($usage) {
            $payload['help'] = [
                'Valid output modes: toon, text, human, json, events.',
                'Run `tell --help` for all options and examples.',
            ];
        }
        $mode = (string) $input->getOption('output');
        (new StructuredOutput($output))->write(
            $payload,
            json: in_array($mode, ['json', 'events'], true),
        );
    }
}
