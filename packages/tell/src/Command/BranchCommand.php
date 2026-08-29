<?php

declare(strict_types=1);

namespace Cognesy\Tell\Command;

use Cognesy\Tell\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Operational\OperationalPlane;
use Cognesy\Tell\Operational\PlaneOperation;
use Cognesy\Tell\Render\StructuredOutput;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Arena\Provenance;
use Cognesy\Tell\Workspace\Arena\Ref;
use Cognesy\Tell\Workspace\Branch\BranchCatalog;
use Cognesy\Tell\Workspace\Branch\BranchName;
use Cognesy\Tell\Workspace\Branch\BranchResolver;
use Cognesy\Tell\Workspace\Branch\Storage\BranchConfigStore;
use Cognesy\Tell\Workspace\WorkspaceException;
use Cognesy\Tell\Workspace\WorkspaceState;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class BranchCommand extends Command implements CanDescribeOperationalPlane
{
    private readonly TellAgentFactory $agents;

    public function __construct(?TellAgentFactory $agents = null) {
        $this->agents = $agents ?? TellAgentFactory::installed();
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
        $workspace = $this->workspace($input);
        $store = new FilesystemArena($workspace);
        $current = (new BranchResolver($store, $workspace))->resolve();
        $catalog = new BranchCatalog($store, new BranchConfigStore($workspace));
        $branches = $catalog->list((bool) $input->getOption('full'));
        $branches = array_map(static function (array $branch) use ($current): array {
            $branch['current'] = $branch['name'] === $current->branch;

            return $branch;
        }, $branches);
        $payload = [
            'current' => $current->toArray(),
            'count' => count($branches),
            'branches' => $branches,
        ];
        if ($branches === []) {
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
        $workspace = $this->workspace($input);
        $store = new FilesystemArena($workspace);
        $source = match (true) {
            (bool) $input->getOption('empty') => new Ref(null, new Provenance('empty', null, null)),
            $from !== null => $this->fromBranch($store, BranchName::from($from)),
            default => $this->fromCurrent($store, $workspace),
        };
        $created = $store->createRef('branches/' . $name->toString(), $source);
        if (!(bool) $input->getOption('empty')) {
            $sourceName = $from === null
                ? (new BranchResolver($store, $workspace))->resolve()->branch
                : BranchName::from($from)->toString();
            (new BranchConfigStore($workspace))->inherit($sourceName, $name->toString());
        }
        $payload = (new BranchCatalog($store, new BranchConfigStore($workspace)))->show($name);
        $payload['created'] = true;
        $payload['source'] = $created->provenance?->toArray();
        (new StructuredOutput($output))->write($payload, json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }

    private function show(BranchName $name, InputInterface $input, OutputInterface $output): int {
        $workspace = $this->workspace($input);
        $store = new FilesystemArena($workspace);
        $payload = (new BranchCatalog($store, new BranchConfigStore($workspace)))->show($name);
        $payload['current'] = $name->toString() === (new BranchResolver($store, $workspace))->resolve()->branch;
        (new StructuredOutput($output))->write($payload, json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }

    private function fromCurrent(FilesystemArena $store, WorkspaceState $workspace): Ref {
        $selection = (new BranchResolver($store, $workspace))->resolve();
        $current = $store->readRef($selection->ref);

        return new Ref($current->head, new Provenance('current', $selection->branch, $current->head));
    }

    private function fromBranch(FilesystemArena $store, BranchName $source): Ref {
        $ref = $store->readOptionalRef('branches/' . $source->toString());
        if ($ref === null) {
            throw new InvalidArgumentException("Tell branch '{$source->toString()}' does not exist.");
        }

        return new Ref($ref->head, new Provenance('branch', $source->toString(), $ref->head));
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

    private function workspace(InputInterface $input): WorkspaceState {
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
        $workspace = $this->agents->workspace()->discover($project);
        if ($workspace === null) {
            throw new WorkspaceException('Tell branch requires an initialized workspace; run `tell init` first.');
        }

        return $workspace;
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
