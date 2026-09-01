<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Command;

use Cognesy\Tell\Adapter\Console\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Adapter\Console\Operational\OperationalPlane;
use Cognesy\Tell\Adapter\Console\Operational\PlaneOperation;
use Cognesy\Tell\Adapter\Console\Render\StructuredOutput;
use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use Cognesy\Tell\Core\Workspace\WorkspaceException;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CheckoutCommand extends Command implements CanDescribeOperationalPlane
{
    public function __construct(private readonly CanAccessTellConversations $conversations) {
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
            $branches = $this->conversations->branches($directory);
            $previous = $branches->current();
            $value = $input->getArgument('name');
            if (!is_string($value) || $value === '') {
                throw new InvalidArgumentException('Branch name is required.');
            }
            $selected = $branches->checkout($value);
            (new StructuredOutput($output))->write([
                'previous' => ['name' => $previous->name, 'source' => $previous->source],
                'branch' => ['name' => $selected->name, 'source' => $selected->source],
                'changed' => $previous->name !== $selected->name,
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
