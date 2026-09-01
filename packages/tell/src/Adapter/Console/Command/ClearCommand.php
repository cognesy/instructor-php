<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Command;

use Cognesy\Tell\Adapter\Console\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Adapter\Console\Operational\OperationalPlane;
use Cognesy\Tell\Adapter\Console\Operational\PlaneOperation;
use Cognesy\Tell\Adapter\Console\Render\StructuredOutput;
use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use Cognesy\Tell\Core\Contract\Workspace\CanMaintainTellConversation;
use Cognesy\Tell\Core\Workspace\WorkspaceException;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Explicitly moves one canonical conversation selector to its empty state.
 */
final class ClearCommand extends Command implements CanDescribeOperationalPlane
{
    public function __construct(private readonly CanAccessTellConversations $conversations) {
        parent::__construct('clear');
    }

    #[Override]
    protected function configure(): void {
        $this->setDescription('Clear one Tell canonical conversation without deleting history')
            ->setHelp(<<<'HELP'
Move the selected canonical ref to its empty state. Prior immutable objects stay
available for audit, and the command neither runs inference nor deletes traces,
configuration, or other refs.

Examples:
  tell clear
  tell clear --session review-1
  tell clear --json
HELP)
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Workspace directory', '')
            ->addOption('session', 's', InputOption::VALUE_REQUIRED, 'Clear a named workspace session')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Clear one branch without checking it out')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $session = $this->session($input);
            $requested = $input->getOption('branch');
            if ($requested !== null && $requested !== '' && $session !== null) {
                throw new InvalidArgumentException('--branch and --session cannot be used together.');
            }
            if ($requested !== null && !is_string($requested)) {
                throw new InvalidArgumentException('Tell branch selector must be a string.');
            }
            $conversation = $this->conversation($this->directory($input), $session, $requested);
            $result = $conversation->clear();

            (new StructuredOutput($output))->write([
                'selector' => $result->selector,
                'previousHead' => $result->previousHead,
                'head' => $result->head,
                'empty' => $result->isEmpty(),
                'changed' => $result->changed(),
                'message' => match ($result->previousHead) {
                    null => 'Selected canonical conversation is already empty.',
                    default => 'Selected canonical conversation was cleared; prior immutable objects remain available.',
                },
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
            command: 'clear',
            responsibility: 'Atomically move one verified canonical conversation selector to empty state.',
            ownedState: 'The selected project-local arena ref only; immutable source objects remain untouched.',
            input: 'Optional workspace directory and named-session selector.',
            output: 'Previous and resulting head identities plus a deterministic changed/empty result.',
            authority: 'Validate canonical lineage and mutate only the selected ref; no inference, tools, deletions, or user-home writes.',
            degradedBehavior: 'Reports invalid selectors, corrupt lineage, stale heads, and storage failures without partial mutation.',
        );
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

    private function session(InputInterface $input): ?string {
        $value = $input->getOption('session');
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('Tell session selector must be a string.');
        }

        return $value;
    }

    private function conversation(string $directory, ?string $session, mixed $branch): CanMaintainTellConversation {
        return match (true) {
            $session !== null => $this->conversations->conversation($directory, $session),
            is_string($branch) && $branch !== '' => $this->conversations->branch($directory, $branch),
            default => $this->conversations->current($directory),
        };
    }

    private function writeError(OutputInterface $output, string $message, bool $usage, bool $json): void {
        $payload = ['error' => $message];
        if ($usage) {
            $payload['help'] = ['Run `tell clear --help` for valid selectors and examples.'];
        }
        (new StructuredOutput($output))->write($payload, json: $json);
    }
}
