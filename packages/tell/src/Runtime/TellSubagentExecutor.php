<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Agents\Capability\Subagent\CanExecuteSubagent;
use Cognesy\Agents\Capability\Subagent\SubagentExecutionResult;
use Cognesy\Agents\Capability\Subagent\SubagentInvocation;
use Cognesy\Tell\Console\TellOptions;
use Cognesy\Tell\Workspace\Arena\Exception\RefConflict;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Arena\Provenance;
use Cognesy\Tell\Workspace\Arena\Ref;
use Cognesy\Tell\Workspace\Branch\BranchName;
use Cognesy\Tell\Workspace\Branch\ResolvedBranch;
use Cognesy\Tell\Workspace\Branch\Storage\BranchConfigStore;
use Cognesy\Tell\Workspace\Execution\TurnRunner;
use Override;
use RuntimeException;
use Throwable;

/** Persists a generic Agents subagent run on one Tell-owned child branch. */
final readonly class TellSubagentExecutor implements CanExecuteSubagent
{
    public function __construct(
        private TellAgentFactory $agents,
        private TellOptions $parentOptions,
        private TellDelegationScope $scope,
        private ?TellDiagnostics $diagnostics = null,
    ) {}

    #[Override]
    public function execute(SubagentInvocation $invocation): SubagentExecutionResult {
        if ($this->scope->depth >= 1) {
            throw new RuntimeException('Tell child delegation is limited to one depth.');
        }

        $arena = new FilesystemArena($this->scope->workspace);
        $parent = $arena->readRef($this->scope->parent->ref);
        $child = $this->createChild($arena, $parent, $invocation);
        (new BranchConfigStore($this->scope->workspace))->inherit($this->scope->parent->branch, $child->toString());

        $options = $this->childOptions($invocation, $child);
        $definition = $this->agents->definition($options);
        $loop = $this->agents->build(
            options: $options,
            definition: $definition,
            cancellation: $this->scope->cancellation,
            delegation: new TellDelegationScope(
                $this->scope->workspace,
                new ResolvedBranch($child->toString(), 'branches/' . $child->toString(), true),
                depth: 1,
                cancellation: $this->scope->cancellation,
            ),
            diagnostics: $this->diagnostics,
        );
        $this->agents->attachExecutionTrace($loop, $options);

        try {
            $state = (new TurnRunner($arena, ref: 'branches/' . $child->toString()))->execute($loop, $definition, $invocation->prompt);
            $head = $arena->readRef('branches/' . $child->toString())->head?->toString();

            return new SubagentExecutionResult($state, [
                'success' => true,
                'branch' => $child->toString(),
                'status' => $state->status()?->value,
                'head' => $head,
                'report' => $this->bound($state->finalResponse()->toString()),
                'definition' => $definition->name,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Tell delegated child failed on ' . $child->toString() . '.', previous: $exception);
        }
    }

    private function bound(string $report): string {
        $limit = $this->parentOptions->policy->maxToolOutputChars;
        if (strlen($report) <= $limit) {
            return $report;
        }

        return substr($report, 0, $limit) . '…';
    }

    private function createChild(FilesystemArena $arena, Ref $parent, SubagentInvocation $invocation): BranchName {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $child = BranchName::child('agent-' . bin2hex(random_bytes(12)));
            $head = $invocation->context === 'fresh' ? null : $parent->head;
            try {
                $arena->createRef('branches/' . $child->toString(), new Ref(
                    $head,
                    new Provenance('agent', $this->scope->parent->branch, $parent->head, [
                        'kind' => 'delegated-child',
                        'context' => $invocation->context,
                        'definition' => $invocation->definition->name,
                        'executionId' => 'delegation-' . bin2hex(random_bytes(12)),
                        'configuration' => [
                            'policy' => $this->effectivePolicyProvenance(),
                        ],
                    ]),
                ));

                return $child;
            } catch (RefConflict) {
                continue;
            }
        }

        throw new RuntimeException('Tell could not reserve a unique child branch.');
    }

    private function childOptions(SubagentInvocation $invocation, BranchName $child): TellOptions {
        $values = (new BranchConfigStore($this->scope->workspace))->runtimeValues($child->toString());
        $parent = $this->parentOptions;

        return (new TellOptions(
            prompt: $invocation->prompt,
            agent: $invocation->definition->name,
            connection: $parent->connection,
            model: $parent->model,
            reasoningEffort: $parent->reasoningEffort,
            dsn: $parent->dsn,
            branch: $child->toString(),
            directory: $parent->directory,
            tools: $parent->tools,
            answers: $parent->answers,
            maxSteps: $parent->maxSteps,
            output: $parent->output,
            transient: false,
            connectionExplicit: $parent->connectionExplicit,
            modelExplicit: $parent->modelExplicit,
            reasoningEffortExplicit: $parent->reasoningEffortExplicit,
            toolsExplicit: $parent->toolsExplicit,
            policyOverrides: $parent->policyOverrides,
            policy: $parent->policy,
        ))->withBranchConfig($values);
    }

    /** @return array<string, 'cli'|'branch'|'project'|'user'|'bundled'> */
    private function effectivePolicyProvenance(): array {
        return $this->parentOptions->policy->provenance();
    }
}
