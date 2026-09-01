<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Command;

use Cognesy\Tell\Adapter\Console\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Adapter\Console\Operational\OperationalPlane;
use Cognesy\Tell\Adapter\Console\Operational\PlaneOperation;
use Cognesy\Tell\Adapter\Console\Render\StructuredOutput;
use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use Cognesy\Tell\Core\Workspace\WorkspaceException;
use Cognesy\Tell\Data\TellBranchSelection;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ResetCommand extends Command implements CanDescribeOperationalPlane
{
    public function __construct(private readonly CanAccessTellConversations $conversations) {
        parent::__construct('reset');
    }

    #[Override]
    protected function configure(): void {
        $this->setDescription('Move a Tell branch head backwards without deleting immutable history')
            ->setHelp('Use either --steps N or --to HASH. This never deletes objects and has no public reflog; create a recovery branch first when needed.')
            ->addOption('steps', null, InputOption::VALUE_REQUIRED, 'Positive number of parent links to move backwards')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Verified reachable canonical ancestor hash')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Reset one branch without checking it out')
            ->addOption('session', 's', InputOption::VALUE_REQUIRED, 'Rejected: reset applies only to branches')
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Workspace directory', '')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $session = $input->getOption('session');
            $requested = $input->getOption('branch');
            if (($session !== null && $session !== '') || ($requested !== null && !is_string($requested))) {
                throw new InvalidArgumentException('--session is not supported by reset.');
            }
            $directory = (string) $input->getOption('dir');
            $cwd = getcwd();
            $directory = $directory !== '' ? $directory : (is_string($cwd) ? $cwd : '.');
            $branches = $this->conversations->branches($directory);
            $selection = $requested === null || $requested === ''
                ? $branches->current()
                : new TellBranchSelection($requested, 'invocation');
            $steps = $input->getOption('steps');
            $to = $input->getOption('to');
            if (($steps === null || $steps === '') === ($to === null || $to === '')) {
                throw new InvalidArgumentException('Specify exactly one of --steps N or --to HASH.');
            }
            $result = match (true) {
                is_string($to) && $to !== '' => $branches->resetTo($selection->name, $to),
                !is_string($steps) || !ctype_digit($steps) || (int) $steps < 1 || (int) $steps > 1_000
                    => throw new InvalidArgumentException('--steps must be a positive integer no greater than 1000.'),
                default => $branches->reset($selection->name, (int) $steps),
            };
            (new StructuredOutput($output))->write([
                'branch' => ['name' => $selection->name, 'source' => $selection->source],
                'previousHead' => $result->previousHead,
                'head' => $result->head,
                'distance' => $result->distance,
                'changed' => $result->changed,
                'message' => 'Immutable history is retained; create a branch before reset when durable recovery is required.',
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
            command: 'reset',
            responsibility: 'Move one selected branch ref to a verified prior ancestor.',
            ownedState: 'The selected branch ref; immutable canonical objects are retained.',
            input: 'Exactly one bounded step count or canonical ancestor hash.',
            output: 'Selected branch and before/after head identities.',
            authority: 'Validates ancestry then performs one compare-and-swap.',
            degradedBehavior: 'Rejects invalid ancestry and stale updates without moving the ref.',
        );
    }

}
