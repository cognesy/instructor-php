<?php

declare(strict_types=1);

namespace Cognesy\Tell\Command;

use Cognesy\Tell\Canonical\CanonicalConversationRoot;
use Cognesy\Tell\Canonical\CanonicalHash;
use Cognesy\Tell\Canonical\CanonicalTurn;
use Cognesy\Tell\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Operational\OperationalPlane;
use Cognesy\Tell\Operational\PlaneOperation;
use Cognesy\Tell\Render\StructuredOutput;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\BranchResolver;
use Cognesy\Tell\Workspace\WorkspaceException;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ResetCommand extends Command implements CanDescribeOperationalPlane
{
    private const int MAX_STEPS = 1_000;

    private const int MAX_LINEAGE_DEPTH = 1_000;

    public function __construct(private readonly TellAgentFactory $agents)
    {
        parent::__construct('reset');
    }

    #[Override]
    protected function configure(): void
    {
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $session = $input->getOption('session');
            $requested = $input->getOption('branch');
            if (($session !== null && $session !== '') || ($requested !== null && ! is_string($requested))) {
                throw new InvalidArgumentException('--session is not supported by reset.');
            }
            $directory = (string) $input->getOption('dir');
            $cwd = getcwd();
            $directory = $directory !== '' ? $directory : (is_string($cwd) ? $cwd : '.');
            $workspace = $this->agents->workspace()->discover($directory);
            if ($workspace === null) {
                throw new WorkspaceException('Tell reset requires an initialized workspace; run `tell init` first.');
            }
            $arena = new ArenaStore($workspace);
            $branch = (new BranchResolver($arena))->resolve($requested === '' ? null : $requested);
            $before = $arena->readRef($branch->ref)->head;
            [$target, $distance] = $this->target($arena, $before, $input);
            $result = $target === null
                ? $arena->compareAndSwapToEmpty($branch->ref, $before)
                : $arena->compareAndSwap($branch->ref, $before, $target);
            (new StructuredOutput($output))->write([
                'branch' => $branch->toArray(),
                'previousHead' => $before?->toString(),
                'head' => $result->head?->toString(),
                'distance' => $distance,
                'changed' => ($before?->toString()) !== ($result->head?->toString()),
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
    public function planeOperation(): PlaneOperation
    {
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

    /** @return array{?CanonicalHash, int} */
    private function target(ArenaStore $arena, ?CanonicalHash $head, InputInterface $input): array
    {
        $steps = $input->getOption('steps');
        $to = $input->getOption('to');
        if (($steps === null || $steps === '') === ($to === null || $to === '')) {
            throw new InvalidArgumentException('Specify exactly one of --steps N or --to HASH.');
        }
        $lineage = $this->lineage($arena, $head);
        if ($to !== null && is_string($to)) {
            $target = new CanonicalHash($to);
            foreach ($lineage as $distance => $candidate) {
                if ($candidate !== null && $candidate->equals($target)) {
                    return [$candidate, $distance];
                }
            }
            throw new InvalidArgumentException('Reset target must be a verified reachable ancestor of the selected head.');
        }
        if (! is_string($steps) || ! ctype_digit($steps) || (int) $steps < 1 || (int) $steps > self::MAX_STEPS) {
            throw new InvalidArgumentException('--steps must be a positive integer no greater than '.self::MAX_STEPS.'.');
        }
        $distance = (int) $steps;
        if (! array_key_exists($distance, $lineage)) {
            throw new InvalidArgumentException('Reset steps exceed the selected branch ancestry.');
        }
        return [$lineage[$distance], $distance];
    }

    /** @return array<int, ?CanonicalHash> */
    private function lineage(ArenaStore $arena, ?CanonicalHash $head): array
    {
        $lineage = [0 => $head];
        $seen = [];
        $cursor = $head;
        for ($distance = 1; $cursor !== null; $distance++) {
            if ($distance > self::MAX_LINEAGE_DEPTH) {
                throw new WorkspaceException('Tell arena lineage exceeds the reset safety limit.');
            }
            $id = $cursor->toString();
            if (isset($seen[$id])) {
                throw new WorkspaceException('Tell arena lineage contains a cycle.');
            }
            $seen[$id] = true;
            $record = $arena->get($cursor);
            $cursor = match (true) {
                $record instanceof CanonicalTurn => $record->lineage()->parent(),
                $record instanceof CanonicalConversationRoot => null,
                default => throw new WorkspaceException('Tell branch head is not canonical conversation history.'),
            };
            $lineage[$distance] = $cursor;
        }
        return $lineage;
    }
}
