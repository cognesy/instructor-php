<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Command;

use Cognesy\Tell\Adapter\Console\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Adapter\Console\Operational\OperationalPlane;
use Cognesy\Tell\Adapter\Console\Operational\PlaneOperation;
use Cognesy\Tell\Adapter\Console\Render\FieldSelection;
use Cognesy\Tell\Adapter\Console\Render\StructuredOutput;
use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SessionsCommand extends Command implements CanDescribeOperationalPlane
{
    public function __construct(private readonly CanAccessTellConversations $conversations) {
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
        foreach ($this->conversations->sessions($this->directory($input))->list() as $session) {
            $sessionsById[$session->details['sessionId']] = $session->details;
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

    private function show(string $id, InputInterface $input, OutputInterface $output): int {
        $workspaceSession = $this->conversations->sessions($this->directory($input))->show(
            $id,
            (bool) $input->getOption('full'),
        );
        if ($workspaceSession === null) {
            throw new InvalidArgumentException("Tell session '{$id}' does not exist in this workspace.");
        }
        (new StructuredOutput($output))->write($workspaceSession->details, json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }

    private function remove(string $id, InputInterface $input, OutputInterface $output): int {
        $result = $this->conversations->sessions($this->directory($input))->remove($id);
        (new StructuredOutput($output))->write([
            'sessionId' => $result->sessionId,
            'removed' => $result->removed,
            'message' => match ($result->removed) {
                true => 'Session removed.',
                false => 'Session did not exist.',
            },
        ], json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }

    private function requiredId(mixed $id): string {
        if (!is_string($id) || $id === '') {
            throw new InvalidArgumentException('Session ID is required for show and rm.');
        }

        return $id;
    }

    private function directory(InputInterface $input): string {
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

        return $project;
    }
}
