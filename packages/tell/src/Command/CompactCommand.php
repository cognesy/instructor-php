<?php

declare(strict_types=1);

namespace Cognesy\Tell\Command;

use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Operational\OperationalPlane;
use Cognesy\Tell\Operational\PlaneOperation;
use Cognesy\Tell\Render\StructuredOutput;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Runtime\TellOptions;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\BranchResolver;
use Cognesy\Tell\Workspace\BranchConfigStore;
use Cognesy\Tell\Workspace\SessionCompatibilityRef;
use Cognesy\Tell\Workspace\TellWorkspace;
use Cognesy\Tell\Workspace\WorkspaceCompactionRunner;
use Cognesy\Tell\Workspace\WorkspaceException;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Explicitly summarizes one canonical conversation into a provenance-linked head.
 */
final class CompactCommand extends Command implements CanDescribeOperationalPlane
{
    private const int MAX_HINT_CHARACTERS = 500;

    private readonly TellAgentFactory $agents;

    public function __construct(?TellAgentFactory $agents = null)
    {
        $this->agents = $agents ?? TellAgentFactory::installed();
        parent::__construct('compact');
    }

    #[Override]
    protected function configure(): void
    {
        $this->setDescription('Explicitly summarize one Tell canonical conversation')
            ->setHelp(<<<'HELP'
Run one configured inference request that replaces the selected canonical ref
with a concise summary. The replaced immutable history is retained and named in
the new turn's compaction provenance; no automatic compaction or deletion occurs.

Examples:
  tell compact
  tell compact "prioritize unresolved release work" --session review-1
  tell compact --connection deepseek --model deepseek-v4-flash --json
HELP)
            ->addArgument('hint', InputArgument::OPTIONAL, 'Optional bounded focus for the summary')
            ->addOption('agent', 'a', InputOption::VALUE_REQUIRED, 'Agent definition name', 'default')
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'LLM connection preset name', 'openai')
            ->addOption('model', 'm', InputOption::VALUE_REQUIRED, 'Model override', '')
            ->addOption('dsn', 'd', InputOption::VALUE_REQUIRED, 'Inline LLM DSN', '')
            ->addOption('session', 's', InputOption::VALUE_REQUIRED, 'Compact a named workspace session')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Compact one branch without checking it out')
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Workspace directory', '')
            ->addOption('max-steps', null, InputOption::VALUE_REQUIRED, 'Maximum agent steps', '10')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $hint = $this->hint($input);
            $options = $this->options($input);
            $workspace = $this->workspace($options->directory);
            $session = $this->session($input);
            $requested = $input->getOption('branch');
            if ($requested !== null && $requested !== '' && $session !== null) {
                throw new InvalidArgumentException('--branch and --session cannot be used together.');
            }
            if ($requested !== null && ! is_string($requested)) {
                throw new InvalidArgumentException('Tell branch selector must be a string.');
            }
            $arena = new ArenaStore($workspace);
            $branch = $session === null ? (new BranchResolver($arena))->resolve($requested === '' ? null : $requested) : null;
            if ($branch !== null) {
                $options = $options->withBranchConfig((new BranchConfigStore($workspace))->runtimeValues($branch->branch));
            }
            $definition = $this->agents->definition($options);
            $this->agents->assertReady($options);
            $loop = $this->agents->build($options, $definition);
            $this->agents->attachExecutionTrace($loop, $options);
            $result = (new WorkspaceCompactionRunner(
                arena: $arena,
                ref: match ($session) {
                    null => $branch->ref,
                    default => (new SessionCompatibilityRef($session))->refName(),
                },
            ))->execute($loop, $definition, $hint);

            (new StructuredOutput($output))->write([
                'selector' => match ($session) {
                    null => $branch->branch === 'main'
                        ? ['type' => 'main', 'name' => 'main']
                        : ['type' => 'branch', 'name' => $branch->branch, 'source' => $branch->invocationLocal ? 'invocation' : 'current'],
                    default => ['type' => 'session', 'name' => $session->toString()],
                },
                ...$result->toArray(),
                'message' => 'Selected canonical conversation was compacted; replaced immutable history remains available.',
            ], json: (bool) $input->getOption('json'));

            return Command::SUCCESS;
        } catch (InvalidArgumentException $error) {
            $this->writeError($output, $error->getMessage(), true, (bool) $input->getOption('json'));

            return Command::INVALID;
        } catch (Throwable $error) {
            $this->writeError($output, $error->getMessage(), false, (bool) $input->getOption('json'));

            return Command::FAILURE;
        }
    }

    #[Override]
    public function planeOperation(): PlaneOperation
    {
        return new PlaneOperation(
            plane: OperationalPlane::Data,
            command: 'compact [hint]',
            responsibility: 'Explicitly infer and publish one provenance-linked canonical summary for a selected conversation.',
            ownedState: 'The selected project-local arena ref plus immutable canonical summary and trace records.',
            input: 'Optional bounded focus hint, workspace/session selector, and normal Tell agent and inference configuration.',
            output: 'Source and resulting head identities with before/after context counts; source transcript is omitted.',
            authority: 'Configured inference and tools, one execution trace, immutable arena object writes, and a conditional selected-ref update.',
            degradedBehavior: 'Reports unavailable configuration, invalid lineage, invalid summaries, or stale heads without moving the selected ref.',
        );
    }

    private function hint(InputInterface $input): string
    {
        $hint = $input->getArgument('hint');
        if ($hint === null || $hint === '') {
            return '';
        }
        if (! is_string($hint)) {
            throw new InvalidArgumentException('Tell compact hint must be a string.');
        }
        if (mb_strlen($hint) > self::MAX_HINT_CHARACTERS) {
            throw new InvalidArgumentException(
                'Tell compact hint must be at most '.self::MAX_HINT_CHARACTERS.' characters.',
            );
        }

        return $hint;
    }

    private function options(InputInterface $input): TellOptions
    {
        $directory = (string) $input->getOption('dir');
        $cwd = getcwd();
        $project = match (true) {
            $directory !== '' => $directory,
            is_string($cwd) => $cwd,
            default => '.',
        };

        return new TellOptions(
            prompt: 'Compact Tell workspace context.',
            agent: (string) $input->getOption('agent'),
            connection: (string) $input->getOption('connection'),
            model: (string) $input->getOption('model'),
            dsn: (string) $input->getOption('dsn'),
            branch: ($input->getOption('branch') ?: null),
            directory: $project,
            maxSteps: (int) $input->getOption('max-steps'),
            connectionExplicit: $input->hasParameterOption(['--connection', '-c'], true),
            modelExplicit: $input->hasParameterOption(['--model', '-m'], true),
        );
    }

    private function workspace(string $directory): TellWorkspace
    {
        $workspace = $this->agents->workspace()->discover($directory);
        if ($workspace === null) {
            throw new WorkspaceException('Tell compact requires an initialized workspace; run `tell init` first.');
        }

        return $workspace;
    }

    private function session(InputInterface $input): ?SessionId
    {
        $value = $input->getOption('session');
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException('Tell session selector must be a string.');
        }

        return SessionId::from($value);
    }

    private function writeError(OutputInterface $output, string $message, bool $usage, bool $json): void
    {
        $payload = ['error' => $message];
        if ($usage) {
            $payload['help'] = ['Run `tell compact --help` for valid selectors and configuration options.'];
        }
        (new StructuredOutput($output))->write($payload, json: $json);
    }
}
