<?php

declare(strict_types=1);

namespace Cognesy\Tell\Command;

use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Operational\OperationalPlane;
use Cognesy\Tell\Operational\PlaneOperation;
use Cognesy\Tell\Render\FieldSelection;
use Cognesy\Tell\Render\StructuredOutput;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Session\SessionCatalog;
use Cognesy\Tell\Workspace\Session\SessionRef;
use Cognesy\Tell\Workspace\WorkspaceState;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SessionsCommand extends Command implements CanDescribeOperationalPlane
{
    private readonly TellAgentFactory $agents;

    public function __construct(?TellAgentFactory $agents = null) {
        $this->agents = $agents ?? TellAgentFactory::installed();
        parent::__construct('sessions');
    }

    #[Override]
    protected function configure(): void {
        $this->setDescription('List, show, or remove persisted sessions')
            ->setHelp(<<<'HELP'
List sessions, inspect one session, or remove one session.

Examples:
  tell sessions
  tell sessions --dir .
  tell sessions show review-1
  tell sessions show review-1 --full
  tell sessions rm review-1
HELP)
            ->addArgument('action', InputArgument::OPTIONAL, 'list, show, or rm', 'list')
            ->addArgument('id', InputArgument::OPTIONAL, 'Session ID')
            ->addOption('fields', null, InputOption::VALUE_REQUIRED, 'Comma-separated list fields', '')
            ->addOption('full', null, InputOption::VALUE_NONE, 'Show complete session message content')
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Workspace directory for arena-backed sessions', '')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $action = (string) $input->getArgument('action');
            $id = $input->getArgument('id');

            return match ($action) {
                'list' => $this->list($input, $output),
                'show' => $this->show($this->requiredId($id), $input, $output),
                'rm' => $this->remove($this->requiredId($id), $input, $output),
                default => throw new InvalidArgumentException("Unknown sessions action: {$action}"),
            };
        } catch (InvalidArgumentException $error) {
            (new StructuredOutput($output))->write([
                'error' => $error->getMessage(),
                'help' => [
                    'Valid actions: list, show, rm.',
                    'Run `tell sessions --help` for examples.',
                ],
            ], json: (bool) $input->getOption('json'));

            return Command::INVALID;
        }
    }

    #[Override]
    public function planeOperation(): PlaneOperation {
        return new PlaneOperation(
            plane: OperationalPlane::Management,
            command: 'sessions',
            responsibility: 'Observe and clear Arena-backed named workspace sessions.',
            ownedState: 'Named session refs and immutable canonical records in the selected workspace Arena.',
            input: 'Operator list, show, or remove command targeting an initialized workspace.',
            output: 'Bounded session inventory/detail or an idempotent named-session clear result.',
            authority: 'Read canonical named sessions and atomically clear one explicitly named session ref.',
            degradedBehavior: 'Stateless data-plane turns continue without a workspace; named-session operations fail explicitly.',
        );
    }

    private function list(InputInterface $input, OutputInterface $output): int {
        $sessionsById = [];
        $workspace = $this->workspace($input);
        if ($workspace !== null) {
            foreach ((new SessionCatalog(new FilesystemArena($workspace)))->list() as $session) {
                $sessionsById[$session['sessionId']] = $session;
            }
        }
        ksort($sessionsById, SORT_STRING);
        /** @var list<array<string, mixed>> $sessions */
        $sessions = array_values($sessionsById);
        $fields = FieldSelection::from(
            (string) $input->getOption('fields'),
            ['sessionId', 'status', 'agentName', 'storage', 'source'],
            ['sessionId', 'status', 'version', 'agentName', 'agentLabel', 'createdAt', 'updatedAt', 'storage', 'source'],
        );
        $payload = [
            'count' => count($sessions),
            'sessions' => $fields->project($sessions),
            'help' => [
                'Run `tell sessions show <id>` to inspect a session.',
                'Run `tell sessions rm <id>` to remove a session.',
            ],
        ];
        if ($sessions === []) {
            $payload['message'] = 'No persisted sessions found.';
        }
        (new StructuredOutput($output))->write($payload, json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }

    private function show(SessionId $id, InputInterface $input, OutputInterface $output): int {
        $workspace = $this->workspace($input);
        if ($workspace === null) {
            throw new InvalidArgumentException('Tell sessions require an initialized workspace.');
        }
        $workspaceSession = (new SessionCatalog(new FilesystemArena($workspace)))->show(
            $id,
            (bool) $input->getOption('full'),
        );
        if ($workspaceSession === null) {
            throw new InvalidArgumentException("Tell session '{$id->toString()}' does not exist in this workspace.");
        }
        (new StructuredOutput($output))->write($workspaceSession, json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }

    private function remove(SessionId $id, InputInterface $input, OutputInterface $output): int {
        $workspace = $this->workspace($input);
        if ($workspace === null) {
            throw new InvalidArgumentException('Tell sessions require an initialized workspace.');
        }
        $arena = new FilesystemArena($workspace);
        $ref = (new SessionRef($id))->refName();
        $reference = $arena->readOptionalRef($ref);
        $removed = $reference?->head !== null;
        if ($removed) {
            $arena->compareAndSwapToEmpty($ref, $reference->head);
        }
        (new StructuredOutput($output))->write([
            'sessionId' => $id->toString(),
            'removed' => $removed,
            'message' => match ($removed) {
                true => 'Session removed.',
                false => 'Session did not exist.',
            },
        ], json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }

    private function requiredId(mixed $id): SessionId {
        if (!is_string($id) || $id === '') {
            throw new InvalidArgumentException('Session ID is required for show and rm.');
        }

        return SessionId::from($id);
    }

    private function workspace(InputInterface $input): ?WorkspaceState {
        $directory = (string) $input->getOption('dir');
        $cwd = getcwd();
        $project = match (true) {
            $directory !== '' => $directory,
            is_string($cwd) => $cwd,
            default => '.',
        };
        if (!is_dir($project)) {
            throw new InvalidArgumentException("Workspace directory does not exist: {$project}");
        }

        return $this->agents->workspace()->discover($project);
    }
}
