<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Command;

use Cognesy\Tell\Adapter\Console\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Adapter\Console\Operational\OperationalPlane;
use Cognesy\Tell\Adapter\Console\Operational\PlaneOperation;
use Cognesy\Tell\Adapter\Console\Render\StructuredOutput;
use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use Cognesy\Tell\Core\Workspace\Branch\BranchName;
use Cognesy\Tell\Core\Workspace\WorkspaceException;
use Cognesy\Tell\Data\TellBranchInfo;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class BranchCommand extends Command implements CanDescribeOperationalPlane
{
    public function __construct(private readonly CanAccessTellConversations $conversations) {
        parent::__construct('branch');
    }

    #[Override]
    protected function configure(): void {
        $this->setDescription('List, create, or inspect immutable-head Tell branches')
            ->setHelp(<<<'HELP'
List branches, create one from the checked-out head or another user branch,
and inspect verified branch history. This command never runs inference.

Branch names are 1-64 lowercase ASCII characters, start with a letter, and
may contain letters, digits, and hyphens. `main`, `internal-*`, `session-*`,
and `agent-*` are reserved for creation, checkout, and mutation. Existing
`agent-*` child branches may only be listed or shown. Unicode and uppercase are rejected deliberately
to prevent traversal and case ambiguity across filesystems.

Examples:
  tell branch list --dir .
  tell branch create experiment --dir .
  tell branch create review --from experiment --dir .
  tell branch create scratch --empty --dir .
  tell branch show experiment --dir . --json
HELP)
            ->addArgument('action', InputArgument::OPTIONAL, 'list, create, or show', 'list')
            ->addArgument('name', InputArgument::OPTIONAL, 'Branch name')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Create from an existing user branch')
            ->addOption('empty', null, InputOption::VALUE_NONE, 'Create an empty branch')
            ->addOption('full', null, InputOption::VALUE_NONE, 'Include branch creation provenance in list output')
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Workspace directory', '')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $action = (string) $input->getArgument('action');

            return match ($action) {
                'list' => $this->list($input, $output),
                'create' => $this->create($this->requiredName($input), $input, $output),
                'show' => $this->show($this->requiredStoredName($input), $input, $output),
                default => throw new InvalidArgumentException("Unknown branch action: {$action}"),
            };
        } catch (InvalidArgumentException $error) {
            $this->writeError($output, $error->getMessage(), true, $input);

            return Command::INVALID;
        } catch (WorkspaceException $error) {
            $this->writeError($output, $error->getMessage(), false, $input);

            return Command::FAILURE;
        }
    }

    #[Override]
    public function planeOperation(): PlaneOperation {
        return new PlaneOperation(
            plane: OperationalPlane::Management,
            command: 'branch',
            responsibility: 'Inspect and atomically create user branch refs over immutable canonical history.',
            ownedState: 'Tell-owned branch refs and the project-local current-branch selector.',
            input: 'Explicit branch action, validated branch name, optional source branch, and workspace directory.',
            output: 'Deterministic branch metadata and verified history counts in TOON or JSON.',
            authority: 'Read refs and canonical objects; create one new branch ref only when explicitly requested.',
            degradedBehavior: 'Reports invalid names, missing branches, conflicts, and corrupt lineage without partial output.',
        );
    }

    private function list(InputInterface $input, OutputInterface $output): int {
        $branches = $this->conversations->branches($this->directory($input));
        $current = $branches->current();
        $views = array_map(
            fn (TellBranchInfo $branch): array => $this->payload($branch, (bool) $input->getOption('full')),
            $branches->list((bool) $input->getOption('full')),
        );
        $payload = [
            'current' => ['name' => $current->name, 'source' => $current->source],
            'count' => count($views),
            'branches' => $views,
        ];
        if ($views === []) {
            $payload['message'] = 'No user branches exist; main remains the current branch.';
        }
        (new StructuredOutput($output))->write($payload, json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }

    private function create(BranchName $name, InputInterface $input, OutputInterface $output): int {
        $from = $input->getOption('from');
        if ($from !== null && !is_string($from)) {
            throw new InvalidArgumentException('--from must be a branch name.');
        }
        if ((bool) $input->getOption('empty') && $from !== null) {
            throw new InvalidArgumentException('--empty and --from cannot be used together.');
        }
        $created = $this->conversations->branches($this->directory($input))->create(
            $name->toString(),
            $from,
            (bool) $input->getOption('empty'),
        );
        $payload = $this->payload($created, true);
        $payload['created'] = true;
        $payload['source'] = $created->created;
        (new StructuredOutput($output))->write($payload, json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }

    private function show(BranchName $name, InputInterface $input, OutputInterface $output): int {
        $branch = $this->conversations->branches($this->directory($input))->show($name->toString());
        $payload = $this->payload($branch, true);
        (new StructuredOutput($output))->write($payload, json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }

    private function requiredName(InputInterface $input): BranchName {
        $value = $input->getArgument('name');
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException('Branch name is required for create and show.');
        }

        return BranchName::from($value);
    }

    private function requiredStoredName(InputInterface $input): BranchName {
        $value = $input->getArgument('name');
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException('Branch name is required for create and show.');
        }

        return BranchName::fromStored($value);
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

    /** @return array<string, mixed> */
    private function payload(TellBranchInfo $branch, bool $full): array {
        $payload = [
            'name' => $branch->name,
            'head' => $branch->head,
            'empty' => $branch->empty,
            'turnCount' => $branch->turnCount,
            'configuration' => $branch->configuration,
            'current' => $branch->current,
        ];
        if ($full) {
            $payload['created'] = $branch->created;
        }

        return $payload;
    }

    private function writeError(OutputInterface $output, string $message, bool $usage, InputInterface $input): void {
        $payload = ['error' => $message];
        if ($usage) {
            $payload['help'] = [
                'Valid actions: list, create, show.',
                'Run `tell branch --help` for branch grammar and examples.',
            ];
        }
        (new StructuredOutput($output))->write($payload, json: (bool) $input->getOption('json'));
    }
}
