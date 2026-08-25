<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Agents\Capability\Subagent\CanExecuteSubagent;
use Cognesy\Agents\Capability\Subagent\SubagentExecutionResult;
use Cognesy\Agents\Capability\Subagent\SubagentInvocation;
use Cognesy\Tell\Workspace\ArenaRef;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\BranchConfigStore;
use Cognesy\Tell\Workspace\BranchName;
use Cognesy\Tell\Workspace\BranchProvenance;
use Cognesy\Tell\Workspace\WorkspaceTurnRunner;
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
    ) {}

    #[Override]
    public function execute(SubagentInvocation $invocation): SubagentExecutionResult
    {
        if ($this->scope->depth >= 1) {
            throw new RuntimeException('Tell child delegation is limited to one depth.');
        }

        $arena = new ArenaStore($this->scope->workspace);
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
                new \Cognesy\Tell\Workspace\BranchSelection($child->toString(), 'branches/'.$child->toString(), true),
                depth: 1,
                cancellation: $this->scope->cancellation,
            ),
        );
        $this->agents->attachExecutionTrace($loop, $options);

        try {
            $state = (new WorkspaceTurnRunner($arena, ref: 'branches/'.$child->toString()))->execute($loop, $definition, $invocation->prompt);
            $head = $arena->readRef('branches/'.$child->toString())->head?->toString();

            return new SubagentExecutionResult($state, [
                'success' => true,
                'branch' => $child->toString(),
                'status' => $state->status()?->value,
                'head' => $head,
                'report' => $this->bound($state->finalResponse()->toString()),
                'definition' => $definition->name,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Tell delegated child failed on '.$child->toString().'.', previous: $exception);
        }
    }

    private function bound(string $report): string
    {
        $limit = $this->parentOptions->policy->maxToolOutputChars;
        if (strlen($report) <= $limit) {
            return $report;
        }

        return substr($report, 0, $limit).'…';
    }

    private function createChild(ArenaStore $arena, ArenaRef $parent, SubagentInvocation $invocation): BranchName
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $child = BranchName::child('agent-'.bin2hex(random_bytes(12)));
            $head = $invocation->context === 'fresh' ? null : $parent->head;
            try {
                $arena->createBranch($child, new ArenaRef(
                    $head,
                    new BranchProvenance('agent', $this->scope->parent->branch, $parent->head, [
                        'kind' => 'delegated-child',
                        'context' => $invocation->context,
                        'definition' => $invocation->definition->name,
                        'executionId' => 'delegation-'.bin2hex(random_bytes(12)),
                        'configuration' => [
                            'policy' => $this->effectivePolicyProvenance(),
                        ],
                    ]),
                ));

                return $child;
            } catch (\Cognesy\Tell\Workspace\ArenaRefConflict) {
                continue;
            }
        }

        throw new RuntimeException('Tell could not reserve a unique child branch.');
    }

    private function childOptions(SubagentInvocation $invocation, BranchName $child): TellOptions
    {
        $values = (new BranchConfigStore($this->scope->workspace))->runtimeValues($child->toString());
        $parent = $this->parentOptions;

        return (new TellOptions(
            prompt: $invocation->prompt,
            agent: $invocation->definition->name,
            connection: $parent->connection,
            model: $parent->model,
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
            toolsExplicit: $parent->toolsExplicit,
            policyOverrides: $parent->policyOverrides,
            policy: $parent->policy,
        ))->withBranchConfig($values);
    }

    /** @return array<string, 'cli'|'branch'|'project'|'user'|'bundled'> */
    private function effectivePolicyProvenance(): array
    {
        return $this->parentOptions->policy->provenance();
    }
}
