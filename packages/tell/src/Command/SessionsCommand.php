<?php

declare(strict_types=1);

namespace Cognesy\Tell\Command;

use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Operational\OperationalPlane;
use Cognesy\Tell\Operational\PlaneOperation;
use Cognesy\Tell\Render\ContentPreview;
use Cognesy\Tell\Render\FieldSelection;
use Cognesy\Tell\Render\StructuredOutput;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\TellWorkspace;
use Cognesy\Tell\Workspace\WorkspaceSessionCatalog;
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

    public function __construct(?TellAgentFactory $agents = null)
    {
        $this->agents = $agents ?? TellAgentFactory::installed();
        parent::__construct('sessions');
    }

    #[Override]
    protected function configure(): void
    {
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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
    public function planeOperation(): PlaneOperation
    {
        return new PlaneOperation(
            plane: OperationalPlane::Management,
            command: 'sessions',
            responsibility: 'Observe and perform explicit lifecycle deletion of persisted agent sessions.',
            ownedState: 'FileSessionStore legacy records plus read-only workspace arena session projections.',
            input: 'Operator list, show, or remove command targeting local sessions and an optional workspace.',
            output: 'Bounded session inventory/detail with stable storage and source, or an idempotent legacy deletion result.',
            authority: 'Read local sessions and workspace session projections; delete only an explicitly named session from legacy storage.',
            degradedBehavior: 'Stateless data-plane turns continue when storage is unavailable; session operations fail explicitly.',
        );
    }

    private function list(InputInterface $input, OutputInterface $output): int
    {
        $sessionsById = [];
        foreach ($this->legacySessions() as $session) {
            $sessionsById[$session['sessionId']] = $session;
        }
        $workspace = $this->workspace($input);
        if ($workspace !== null) {
            foreach ((new WorkspaceSessionCatalog(new ArenaStore($workspace)))->list() as $session) {
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

    private function show(SessionId $id, InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace($input);
        if ($workspace !== null) {
            $workspaceSession = (new WorkspaceSessionCatalog(new ArenaStore($workspace)))->show(
                $id,
                (bool) $input->getOption('full'),
            );
            if ($workspaceSession !== null) {
                (new StructuredOutput($output))->write($workspaceSession, json: (bool) $input->getOption('json'));

                return Command::SUCCESS;
            }
        }

        $session = $this->agents->sessions()->getSession($id);
        $preview = ContentPreview::from(
            $session->state()->messages()->toString(),
            (bool) $input->getOption('full'),
        );
        $data = [
            ...$session->info()->toArray(),
            'messageCount' => $session->state()->messages()->count(),
            'messageCharacters' => $preview->characters,
            'messages' => $preview->content,
            'truncated' => $preview->truncated,
            'storage' => 'legacy',
            'source' => 'legacy',
        ];
        if ($preview->truncated) {
            $data['help'] = ['Run `tell sessions show '.$id->toString().' --full` for complete messages.'];
        }
        (new StructuredOutput($output))->write($data, json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }

    private function remove(SessionId $id, InputInterface $input, OutputInterface $output): int
    {
        $repository = $this->agents->sessionRepository();
        $existed = $repository->exists($id);
        $repository->delete($id);
        (new StructuredOutput($output))->write([
            'sessionId' => $id->toString(),
            'removed' => $existed,
            'message' => match ($existed) {
                true => 'Session removed.',
                false => 'Session did not exist.',
            },
        ], json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }

    private function requiredId(mixed $id): SessionId
    {
        if (! is_string($id) || $id === '') {
            throw new InvalidArgumentException('Session ID is required for show and rm.');
        }

        return SessionId::from($id);
    }

    /** @return list<array<string, mixed>> */
    private function legacySessions(): array
    {
        if (! is_dir($this->agents->paths()->sessions)) {
            return [];
        }

        return array_map(
            static fn (array $session): array => [
                ...$session,
                'storage' => 'legacy',
                'source' => 'legacy',
            ],
            array_values($this->agents->sessions()->listSessions()->toArray()),
        );
    }

    private function workspace(InputInterface $input): ?TellWorkspace
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

        return $this->agents->workspace()->discover($project);
    }
}
