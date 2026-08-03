<?php

declare(strict_types=1);

namespace Cognesy\Tell;

use Cognesy\Agents\CanControlAgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Session\Actions\SendMessage;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Data\AgentSessionInfo;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Tell\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Operational\OperationalPlane;
use Cognesy\Tell\Operational\PlaneOperation;
use Cognesy\Tell\Render\EventsRenderer;
use Cognesy\Tell\Render\JsonRenderer;
use Cognesy\Tell\Render\OutputRenderer;
use Cognesy\Tell\Render\StructuredOutput;
use Cognesy\Tell\Render\TextRenderer;
use Cognesy\Tell\Render\ToonRenderer;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Runtime\TellOptions;
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
  tell --output=text "write a commit message"
HELP)
            ->addArgument('prompt', InputArgument::OPTIONAL, 'Prompt')
            ->addOption('agent', 'a', InputOption::VALUE_REQUIRED, 'Agent definition name', 'default')
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'LLM connection preset name', 'openai')
            ->addOption('model', 'm', InputOption::VALUE_REQUIRED, 'Model override', '')
            ->addOption('dsn', 'd', InputOption::VALUE_REQUIRED, 'Inline LLM DSN', '')
            ->addOption('session', 's', InputOption::VALUE_REQUIRED, 'Persist or continue a named session')
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Tool working directory', '')
            ->addOption('tools', null, InputOption::VALUE_REQUIRED, 'Comma-separated tool allow-list', '')
            ->addOption('max-steps', null, InputOption::VALUE_REQUIRED, 'Maximum agent steps', '10')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output: toon, text, json, or events', 'toon');
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
            $options = TellOptions::fromInput($input, $output);
            $this->factory()->assertReady($options);
            $renderer = $this->renderer($options, $output, $stderr);
            $state = $this->executeTurn($options, $renderer);
            $renderer->finish($state);

            return $this->exitCode($state);
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
            ownedState: 'AgentState plus one append-only trace; AgentSession only when --session is explicit.',
            input: 'Prompt plus an immutable resolved AgentDefinition and AgentProfile.',
            output: 'Terminal AgentState projection or typed events, an execution trace, and optional updated AgentSession.',
            authority: 'Inference, resolved tools, its trace target, and optional write access to one named session.',
            degradedBehavior: 'Fails before inference when control resolution fails; trace-write failure does not fail the turn; stateless turns need no session storage.',
        );
    }

    private function executeTurn(TellOptions $options, OutputRenderer $renderer): AgentState
    {
        if ($options->session === null) {
            $definition = $this->factory()->definition($options);
            $loop = $this->factory()->build($options, $definition);
            $this->factory()->attachExecutionTrace($loop, $options);
            $renderer->attach($loop);
            $seed = (new DefinitionStateFactory)
                ->instantiateAgentState($definition)
                ->withUserMessage($options->prompt);

            return $loop->execute($seed);
        }

        $sessionId = SessionId::from($options->session);
        $repository = $this->factory()->sessionRepository();
        if (! $repository->exists($sessionId)) {
            $definition = $this->factory()->definition($options);
            $state = (new DefinitionStateFactory)->instantiateAgentState($definition);
            $repository->create(new AgentSession(
                header: AgentSessionInfo::fresh($sessionId, $definition->name, $definition->label()),
                definition: $definition,
                state: $state,
            ));
        }

        $loopFactory = new class($this->factory(), $options, $renderer) implements CanInstantiateAgentLoop
        {
            public function __construct(
                private readonly TellAgentFactory $agents,
                private readonly TellOptions $options,
                private readonly OutputRenderer $renderer,
            ) {}

            #[Override]
            public function instantiateAgentLoop(AgentDefinition $definition): CanControlAgentLoop
            {
                $loop = $this->agents->build($this->options, $definition);
                $this->agents->attachExecutionTrace($loop, $this->options);
                $this->renderer->attach($loop);

                return $loop;
            }
        };

        $session = $this->factory()->sessions()->execute(
            $sessionId,
            new SendMessage($options->prompt, $loopFactory),
        );

        return $session->state();
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
        $home = getenv('HOME');

        return match (true) {
            is_string($home) && $home !== '' && str_starts_with($binary, $home.DIRECTORY_SEPARATOR) => '~'.substr($binary, strlen($home)),
            default => $binary,
        };
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
        if ($usage) {
            $payload['help'] = [
                'Valid output modes: toon, text, json, events.',
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
