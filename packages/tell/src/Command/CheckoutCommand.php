<?php

declare(strict_types=1);

namespace Cognesy\Tell\Command;

use Cognesy\Tell\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Operational\OperationalPlane;
use Cognesy\Tell\Operational\PlaneOperation;
use Cognesy\Tell\Render\StructuredOutput;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Branch\BranchResolver;
use Cognesy\Tell\Workspace\Branch\Storage\BranchCurrentSelectionStore;
use Cognesy\Tell\Workspace\Branch\Storage\BranchStore;
use Cognesy\Tell\Workspace\WorkspaceException;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CheckoutCommand extends Command implements CanDescribeOperationalPlane
{
    public function __construct(private readonly TellAgentFactory $agents) {
        parent::__construct('checkout');
    }

    #[Override]
    protected function configure(): void {
        $this->setDescription('Persistently select a Tell branch')
            ->addArgument('name', InputArgument::REQUIRED, 'main or an existing user branch')
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Workspace directory', '')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $directory = (string) $input->getOption('dir');
            $cwd = getcwd();
            $directory = $directory !== '' ? $directory : (is_string($cwd) ? $cwd : '.');
            $workspace = $this->agents->workspace()->discover($directory);
            if ($workspace === null) {
                throw new WorkspaceException('Tell checkout requires an initialized workspace; run `tell init` first.');
            }
            $store = new FilesystemArena($workspace);
            $previous = (new BranchResolver($store, $workspace))->resolve();
            $value = $input->getArgument('name');
            if (!is_string($value) || $value === '') {
                throw new InvalidArgumentException('Branch name is required.');
            }
            (new BranchStore($store, new BranchCurrentSelectionStore($workspace)))->checkout($value);
            $selected = (new BranchResolver($store, $workspace))->resolve();
            (new StructuredOutput($output))->write([
                'previous' => $previous->toArray(),
                'branch' => $selected->toArray(),
                'changed' => $previous->branch !== $selected->branch,
            ], json: (bool) $input->getOption('json'));

            return Command::SUCCESS;
        } catch (InvalidArgumentException $error) {
            (new StructuredOutput($output))->write(['error' => $error->getMessage()], json: (bool) $input->getOption('json'));

            return Command::INVALID;
        } catch (WorkspaceException $error) {
            (new StructuredOutput($output))->write(['error' => $error->getMessage()], json: (bool) $input->getOption('json'));

            return Command::FAILURE;
        }
    }

    #[Override]
    public function planeOperation(): PlaneOperation {
        return new PlaneOperation(
            plane: OperationalPlane::Management,
            command: 'checkout NAME',
            responsibility: 'Atomically persist the selected Tell branch.',
            ownedState: 'The project-local symbolic current-branch selector only.',
            input: 'main or an existing validated user branch.',
            output: 'Previous and selected branch metadata.',
            authority: 'Verify a destination ref and head, then atomically replace the selector.',
            degradedBehavior: 'Reports invalid or corrupt branch state without changing the selector.',
        );
    }
}
