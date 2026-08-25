<?php

declare(strict_types=1);

namespace Cognesy\Tell\Command;

use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Operational\OperationalPlane;
use Cognesy\Tell\Operational\PlaneOperation;
use Cognesy\Tell\Render\StructuredOutput;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\SessionCompatibilityRef;
use Cognesy\Tell\Workspace\TellWorkspace;
use Cognesy\Tell\Workspace\WorkspaceConversationReader;
use Cognesy\Tell\Workspace\WorkspaceException;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Explicitly moves one canonical conversation selector to its empty state.
 */
final class ClearCommand extends Command implements CanDescribeOperationalPlane
{
    private readonly TellAgentFactory $agents;

    public function __construct(?TellAgentFactory $agents = null)
    {
        $this->agents = $agents ?? TellAgentFactory::installed();
        parent::__construct('clear');
    }

    #[Override]
    protected function configure(): void
    {
        $this->setDescription('Clear one Tell canonical conversation without deleting history')
            ->setHelp(<<<'HELP'
Move the selected canonical ref to its empty state. Prior immutable objects stay
available for audit, and the command neither runs inference nor deletes legacy
sessions, traces, configuration, or other refs.

Examples:
  tell clear
  tell clear --session review-1
  tell clear --json
HELP)
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Workspace directory', '')
            ->addOption('session', 's', InputOption::VALUE_REQUIRED, 'Clear a named workspace session')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $workspace = $this->workspace($input);
            $session = $this->session($input);
            $arena = new ArenaStore($workspace);
            $conversation = (new WorkspaceConversationReader($arena))->read($session);
            $previousHead = $conversation->head();
            $ref = match ($session) {
                null => 'main',
                default => (new SessionCompatibilityRef($session))->refName(),
            };
            $result = $arena->compareAndSwapToEmpty($ref, $previousHead);

            (new StructuredOutput($output))->write([
                'selector' => $conversation->selector(),
                'previousHead' => $previousHead?->toString(),
                'head' => $result->head?->toString(),
                'empty' => $result->head === null,
                'changed' => $previousHead !== null,
                'message' => match ($previousHead) {
                    null => 'Selected canonical conversation is already empty.',
                    default => 'Selected canonical conversation was cleared; prior immutable objects remain available.',
                },
            ], json: (bool) $input->getOption('json'));

            return Command::SUCCESS;
        } catch (InvalidArgumentException $error) {
            $this->writeError($output, $error->getMessage(), true, (bool) $input->getOption('json'));

            return Command::INVALID;
        } catch (WorkspaceException $error) {
            $this->writeError($output, $error->getMessage(), false, (bool) $input->getOption('json'));

            return Command::FAILURE;
        }
    }

    #[Override]
    public function planeOperation(): PlaneOperation
    {
        return new PlaneOperation(
            plane: OperationalPlane::Management,
            command: 'clear',
            responsibility: 'Atomically move one verified canonical conversation selector to empty state.',
            ownedState: 'The selected project-local arena ref only; immutable source objects remain untouched.',
            input: 'Optional workspace directory and named-session selector.',
            output: 'Previous and resulting head identities plus a deterministic changed/empty result.',
            authority: 'Validate canonical lineage and mutate only the selected ref; no inference, tools, deletions, or user-home writes.',
            degradedBehavior: 'Reports invalid selectors, corrupt lineage, stale heads, and storage failures without partial mutation.',
        );
    }

    private function workspace(InputInterface $input): TellWorkspace
    {
        $directory = (string) $input->getOption('dir');
        $cwd = getcwd();
        $project = match (true) {
            $directory !== '' => $directory,
            is_string($cwd) => $cwd,
            default => '.',
        };
        if (! is_dir($project)) {
            throw new InvalidArgumentException("Workspace directory does not exist: {$project}");
        }
        $workspace = $this->agents->workspace()->discover($project);
        if ($workspace === null) {
            throw new WorkspaceException('Tell clear requires an initialized workspace; run `tell init` first.');
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
            $payload['help'] = ['Run `tell clear --help` for valid selectors and examples.'];
        }
        (new StructuredOutput($output))->write($payload, json: $json);
    }
}
