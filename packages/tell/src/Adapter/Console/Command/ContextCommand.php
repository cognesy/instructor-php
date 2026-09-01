<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Command;

use Cognesy\Tell\Adapter\Console\Symfony\TellOptions;
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
 * Inspects the pre-prompt AgentState assembled from one canonical conversation.
 */
final class ContextCommand extends Command implements CanDescribeOperationalPlane
{
    public function __construct(
        private readonly CanAccessTellConversations $conversations,
    ) {
        parent::__construct('context');
    }

    #[Override]
    protected function configure(): void {
        $this->setDescription('Inspect effective Tell context without inference')
            ->setHelp(<<<'HELP'
Compile the selected canonical conversation into the same AgentState history a
Tell turn uses before appending its next prompt. This is read-only: it does not
build an agent loop, execute tools, or write any state.

Token counts are local BPE estimates. Model-specific capacity remains unknown;
Tell separately reports any finite contextLength configured for this invocation.

Examples:
  tell context
  tell context --session review-1
  tell context --connection deepseek --model deepseek-v4-flash --json
HELP)
            ->addOption('agent', 'a', InputOption::VALUE_REQUIRED, 'Agent definition name', 'default')
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'LLM connection preset name', 'openai')
            ->addOption('model', 'm', InputOption::VALUE_REQUIRED, 'Model override', '')
            ->addOption('dsn', 'd', InputOption::VALUE_REQUIRED, 'Inline LLM DSN', '')
            ->addOption('session', 's', InputOption::VALUE_REQUIRED, 'Inspect a named workspace session')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Inspect one branch without checking it out')
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Workspace directory', '')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $options = $this->options($input);
            $session = $this->session($input);
            $requested = $input->getOption('branch');
            if ($requested !== null && $requested !== '' && $session !== null) {
                throw new InvalidArgumentException('--branch and --session cannot be used together.');
            }
            if ($requested !== null && !is_string($requested)) {
                throw new InvalidArgumentException('Tell branch selector must be a string.');
            }
            if ($session === null) {
                $configuration = $this->conversations->configuration(
                    $options->directory,
                    is_string($requested) && $requested !== '' ? $requested : null,
                )->show();
                $options = $options->withBranchConfig($configuration->values);
            }
            $conversation = $this->conversation($options->directory, $session, $requested);
            $payload = $conversation->context($options->request())->details;
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
            command: 'context',
            responsibility: 'Compile and inspect the selected canonical pre-prompt AgentState without execution.',
            ownedState: 'Read-only projection of verified arena lineage plus selected invocation configuration.',
            input: 'Optional workspace/session selector and agent, connection, model, or DSN selection.',
            output: 'Deterministic counts, explicit token estimate provenance, configured limits, and compaction provenance.',
            authority: 'Read canonical state and configuration only; no inference, loop construction, tool execution, or persistence writes.',
            degradedBehavior: 'Reports missing workspaces, invalid selectors, configuration errors, and corrupt lineage without a partial context.',
        );
    }

    private function options(InputInterface $input): TellOptions {
        $directory = (string) $input->getOption('dir');
        $cwd = getcwd();
        $project = match (true) {
            $directory !== '' => $directory,
            is_string($cwd) => $cwd,
            default => '.',
        };

        return new TellOptions(
            prompt: 'Inspect Tell context.',
            agent: (string) $input->getOption('agent'),
            connection: (string) $input->getOption('connection'),
            model: (string) $input->getOption('model'),
            dsn: (string) $input->getOption('dsn'),
            branch: ($input->getOption('branch') ?: null),
            directory: $project,
            connectionExplicit: $input->hasParameterOption(['--connection', '-c'], true),
            modelExplicit: $input->hasParameterOption(['--model', '-m'], true),
        );
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
            $payload['help'] = ['Run `tell context --help` for valid selectors and configuration options.'];
        }
        (new StructuredOutput($output))->write($payload, json: $json);
    }
}
