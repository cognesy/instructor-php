<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Agent;

use Cognesy\Agents\Capability\Subagent\CanExecuteSubagent;
use Cognesy\Agents\Capability\Subagent\SubagentExecutionResult;
use Cognesy\Agents\Capability\Subagent\SubagentInvocation;
use Cognesy\Tell\Core\Contract\Observation\CanTraceTellExecution;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Agent\CanRecordTellAgentDiagnostics;
use Cognesy\Tell\Data\TellAnswers;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Core\Workspace\Arena\Exception\RefConflict;
use Cognesy\Tell\Core\Workspace\Arena\Provenance;
use Cognesy\Tell\Core\Workspace\Arena\Ref;
use Cognesy\Tell\Core\Workspace\Branch\BranchName;
use Cognesy\Tell\Core\Workspace\Branch\ResolvedBranch;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellWorkspaceArena;
use Cognesy\Tell\Core\Workspace\Execution\TurnRunner;
use Override;
use RuntimeException;
use Throwable;

/** Persists a generic Agents subagent run on one Tell-owned child branch. */
final readonly class TellSubagentExecutor implements CanExecuteSubagent
{
    public function __construct(
        private CanBuildTellAgent $agents,
        private CanTraceTellExecution $tracer,
        private TellRequest $parentOptions,
        private TellDelegationScope $scope,
        private ?CanRecordTellAgentDiagnostics $diagnostics = null,
    ) {}

    #[Override]
    public function execute(SubagentInvocation $invocation): SubagentExecutionResult {
        if ($this->scope->depth >= 1) {
            throw new RuntimeException('Tell child delegation is limited to one depth.');
        }

        $arena = $this->scope->workspace->arena;
        $parent = $arena->readRef($this->scope->parent->ref);
        $child = $this->createChild($arena, $parent, $invocation);
        $this->scope->workspace->branchConfiguration->inherit($this->scope->parent->branch, $child->toString());

        $options = $this->childRequest($invocation, $child);
        $definition = $this->agents->definition($options);
        $loop = $this->agents->build(
            request: $options,
            cancellation: $this->scope->cancellation,
            definition: $definition,
            delegation: new TellDelegationScope(
                $this->scope->workspace,
                new ResolvedBranch($child->toString(), 'branches/' . $child->toString(), true),
                depth: 1,
                cancellation: $this->scope->cancellation,
            ),
            diagnostics: $this->diagnostics,
        );
        $this->tracer->attach($loop, $options);

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

    private function createChild(CanUseTellWorkspaceArena $arena, Ref $parent, SubagentInvocation $invocation): BranchName {
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

    private function childRequest(SubagentInvocation $invocation, BranchName $child): TellRequest {
        $values = $this->scope->workspace->branchConfiguration->runtimeValues($child->toString());
        $parent = $this->parentOptions;

        return (new TellRequest(
            prompt: $invocation->prompt,
            agent: $invocation->definition->name,
            connection: $parent->connection,
            model: $parent->model,
            reasoningEffort: $parent->reasoningEffort,
            dsn: $parent->dsn,
            branch: $child->toString(),
            directory: $parent->directory,
            tools: $parent->tools,
            answers: new TellAnswers(),
            maxSteps: $parent->maxSteps,
            mode: $parent->mode,
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
