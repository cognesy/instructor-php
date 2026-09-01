<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Command;

use Cognesy\Tell\Adapter\Console\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Adapter\Console\Operational\OperationalPlane;
use Cognesy\Tell\Adapter\Console\Operational\PlaneOperation;
use Cognesy\Tell\Adapter\Console\Render\StructuredOutput;
use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use Cognesy\Tell\Core\Contract\Workspace\CanInspectTellConversation;
use Cognesy\Tell\Core\Workspace\WorkspaceException;
use Cognesy\Tell\Data\TellConversationView;
use InvalidArgumentException;
use LogicException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Read-only public views of one verified canonical Tell conversation.
 */
final class WorkspaceInspectionCommand extends Command implements CanDescribeOperationalPlane
{
    private const int DEFAULT_HISTORY_LIMIT = 20;
    private const int MAX_HISTORY_LIMIT = 100;

    public function __construct(
        private readonly string $view,
        private readonly CanAccessTellConversations $conversations,
    ) {
        if (!in_array($view, ['history', 'transcript'], true)) {
            throw new LogicException('Tell workspace inspection view is invalid.');
        }
        parent::__construct($view);
    }

    #[Override]
    protected function configure(): void {
        $this->setDescription($this->description())
            ->setHelp($this->help())
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Workspace directory', '')
            ->addOption('session', 's', InputOption::VALUE_REQUIRED, 'Inspect a named workspace session')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Inspect one branch without checking it out')
            ->addOption('full', null, InputOption::VALUE_NONE, 'Include complete message, tool argument, and result content')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
        if ($this->view === 'history') {
            $this->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Maximum canonical turns to show (1-100)',
                (string) self::DEFAULT_HISTORY_LIMIT,
            );
        }
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $conversation = $this->conversation($input);
            $payload = match ($this->view) {
                'history' => $this->historyPayload(
                    $conversation->history($this->historyLimit($input), (bool) $input->getOption('full')),
                    $this->historyLimit($input),
                ),
                'transcript' => $this->transcriptPayload(
                    $conversation->transcript((bool) $input->getOption('full')),
                    (bool) $input->getOption('full'),
                ),
            };
            (new StructuredOutput($output))->write($payload, json: (bool) $input->getOption('json'));

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
            command: $this->view,
            responsibility: $this->view === 'history'
                ? 'Inspect bounded canonical turn metadata without executing inference.'
                : 'Inspect the ordered semantic canonical transcript without executing inference.',
            ownedState: 'Read-only projection of one validated project-local arena ref.',
            input: 'Optional workspace directory and named-session selector.',
            output: $this->view === 'history'
                ? 'Stable canonical turn identifiers, provenance, and bounded content previews.'
                : 'Ordered semantic messages and bounded tool call/result projections.',
            authority: 'Read verified canonical objects only; no inference, tool execution, or persistence writes.',
            degradedBehavior: 'Reports missing workspace, invalid selectors, and corrupt lineage without returning a partial view.',
        );
    }

    private function description(): string {
        return match ($this->view) {
            'history' => 'Inspect bounded canonical Tell turn history',
            'transcript' => 'Inspect the canonical Tell conversation transcript',
        };
    }

    private function help(): string {
        return match ($this->view) {
            'history' => <<<'HELP'
List verified canonical turns in stable oldest-first order. This is read-only:
it neither resolves a provider nor runs an agent.

Examples:
  tell history
  tell history --limit 10
  tell history --session review-1 --full
  tell history --json
HELP,
            'transcript' => <<<'HELP'
Show ordered semantic messages and tool traces from the selected canonical history.
Default content is bounded; use --full to include complete canonical text.

Examples:
  tell transcript
  tell transcript --session review-1
  tell transcript --full --json
HELP,
        };
    }

    private function conversation(InputInterface $input): CanInspectTellConversation {
        $directory = $this->directory($input);
        $session = $this->session($input);
        $branch = $input->getOption('branch');
        if ($branch !== null && $branch !== '' && $session !== null) {
            throw new InvalidArgumentException('--branch and --session cannot be used together.');
        }
        if ($branch !== null && !is_string($branch)) {
            throw new InvalidArgumentException('Tell branch selector must be a string.');
        }
        return match (true) {
            $session !== null => $this->conversations->conversation($directory, $session),
            is_string($branch) && $branch !== '' => $this->conversations->branch($directory, $branch),
            default => $this->conversations->current($directory),
        };
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

    /** @return array<string, mixed> */
    private function historyPayload(TellConversationView $view, int $limit): array {
        $payload = [
            'selector' => $view->selector,
            'head' => $view->head,
            'root' => $view->root,
            'order' => 'oldest-first',
            'totalCount' => $view->totalCount,
            'count' => count($view->turns),
            'truncated' => $view->truncated,
            'turns' => $view->turns,
        ];
        if ($view->turns === []) {
            $payload['message'] = 'No canonical turns exist for the selected conversation.';
        }
        if ($view->truncated) {
            $payload['help'] = ["Use `tell history --limit {$this->nextHistoryLimit($limit)}` to inspect more turns."];
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function transcriptPayload(TellConversationView $view, bool $full): array {
        $payload = [
            'selector' => $view->selector,
            'head' => $view->head,
            'root' => $view->root,
            'messageCount' => count($view->messages),
            'toolCallCount' => $view->toolCallCount,
            'toolResultCount' => $view->toolResultCount,
            'messages' => $view->messages,
        ];
        if ($view->messages === []) {
            $payload['message'] = 'No canonical messages exist for the selected conversation.';
        }
        if (!$full) {
            $payload['help'] = ['Run `tell transcript --full` to include complete canonical content.'];
        }

        return $payload;
    }

    private function historyLimit(InputInterface $input): int {
        $value = $input->getOption('limit');
        if (!is_string($value) || preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            throw new InvalidArgumentException('--limit must be an integer between 1 and ' . self::MAX_HISTORY_LIMIT . '.');
        }
        $limit = (int) $value;
        if ($limit > self::MAX_HISTORY_LIMIT) {
            throw new InvalidArgumentException('--limit must be an integer between 1 and ' . self::MAX_HISTORY_LIMIT . '.');
        }

        return $limit;
    }

    private function nextHistoryLimit(int $current): int {
        return min(self::MAX_HISTORY_LIMIT, max($current + 1, $current * 2));
    }

    private function writeError(OutputInterface $output, string $message, bool $usage, bool $json): void {
        $payload = ['error' => $message];
        if ($usage) {
            $payload['help'] = ["Run `tell {$this->view} --help` for valid selectors and examples."];
        }
        (new StructuredOutput($output))->write($payload, json: $json);
    }
}
