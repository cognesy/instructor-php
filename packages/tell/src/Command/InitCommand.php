<?php

declare(strict_types=1);

namespace Cognesy\Tell\Command;

use Cognesy\Tell\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Operational\OperationalPlane;
use Cognesy\Tell\Operational\PlaneOperation;
use Cognesy\Tell\Render\StructuredOutput;
use Cognesy\Tell\Workspace\WorkspaceException;
use Cognesy\Tell\Workspace\WorkspaceRepository;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class InitCommand extends Command implements CanDescribeOperationalPlane
{
    public function __construct(private readonly WorkspaceRepository $workspaces = new WorkspaceRepository()) {
        parent::__construct('init');
    }

    #[Override]
    protected function configure(): void {
        $this->setDescription('Initialize durable Tell state in a project')
            ->setHelp(<<<'HELP'
Create the private, versioned Tell workspace for a project. Repeating this
command is a successful no-op when the existing workspace has a supported layout.

Examples:
  tell init
  tell init /path/to/project
  tell init /path/to/project --json
HELP)
            ->addArgument('path', InputArgument::OPTIONAL, 'Project directory', '')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $requested = (string) $input->getArgument('path');
            $cwd = getcwd();
            $directory = match (true) {
                $requested !== '' => $requested,
                is_string($cwd) => $cwd,
                default => '.',
            };
            $result = $this->workspaces->initialize($directory);
            $workspace = $result->workspace;

            (new StructuredOutput($output))->write([
                'workspace' => $workspace->paths->root,
                'arena' => $workspace->paths->arena,
                'schema' => $workspace->schema,
                'status' => $result->created ? 'initialized' : 'already-initialized',
                'help' => [
                    'Run `tell` from this project to inspect the durable workspace.',
                ],
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
    public function planeOperation(): PlaneOperation {
        return new PlaneOperation(
            plane: OperationalPlane::Management,
            command: 'init [path]',
            responsibility: 'Create the project-local Tell workspace after validating the target layout.',
            ownedState: 'A private versioned .tell/arena marker, immutable-object directories, locks, and empty main ref.',
            input: 'An explicit existing project directory or the current working directory.',
            output: 'An initialized workspace path and stable schema version.',
            authority: 'Create Tell-owned project state only; no inference, tools, credentials, or user-home writes.',
            degradedBehavior: 'Refuses malformed, incompatible, file, and symlink layouts without mutation.',
        );
    }

    private function writeError(OutputInterface $output, string $message, bool $usage, bool $json): void {
        $payload = ['error' => $message];
        if ($usage) {
            $payload['help'] = ['Run `tell init [path]` with an existing project directory.'];
        }
        (new StructuredOutput($output))->write($payload, json: $json);
    }
}
