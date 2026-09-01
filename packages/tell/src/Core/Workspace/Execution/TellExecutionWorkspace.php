<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace\Execution;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Tell\Core\Contract\Agent\CanDescribeTellDelegation;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellExecutionWorkspace;
use Cognesy\Tell\Data\TellBranchSelection;
use Cognesy\Tell\Core\Agent\TellDelegationScope;
use Cognesy\Tell\Core\Workspace\Branch\BranchResolver;
use Cognesy\Tell\Core\Workspace\Branch\ResolvedBranch;
use Cognesy\Tell\Core\Workspace\Execution\TransientRunner;
use Cognesy\Tell\Core\Workspace\Execution\TurnRunner;
use Cognesy\Tell\Core\Workspace\Session\SessionRunner;
use Cognesy\Tell\Core\Workspace\TellWorkspaceContext;
use Generator;

/** Backend-neutral execution handle for one discovered workspace. */
final readonly class TellExecutionWorkspace implements CanUseTellExecutionWorkspace
{
    public function __construct(private TellWorkspaceContext $workspace) {}

    #[\Override]
    public function root(): string {
        return $this->workspace->info->root;
    }

    #[\Override]
    public function branch(?string $requested): TellBranchSelection {
        $branch = $this->resolve($requested);

        return new TellBranchSelection(
            $branch->branch,
            $branch->invocationLocal ? 'invocation' : 'current',
        );
    }

    #[\Override]
    public function delegation(
        TellBranchSelection $branch,
        ?CanProvideCancellationSignal $cancellation = null,
    ): CanDescribeTellDelegation {
        return new TellDelegationScope(
            $this->workspace,
            $this->resolved($branch),
            cancellation: $cancellation,
        );
    }

    #[\Override]
    public function turn(
        TellBranchSelection $branch,
        AgentLoop $loop,
        AgentDefinition $definition,
        string $prompt,
    ): Generator {
        return (new TurnRunner(
            $this->workspace->arena,
            ref: $this->ref($branch->name),
        ))->iterate($loop, $definition, $prompt);
    }

    #[\Override]
    public function session(
        SessionId $session,
        AgentLoop $loop,
        AgentDefinition $definition,
        string $prompt,
    ): Generator {
        return (new SessionRunner($this->workspace->arena))
            ->iterate($session, $loop, $definition, $prompt);
    }

    #[\Override]
    public function transient(
        ?SessionId $session,
        ?TellBranchSelection $branch,
        AgentLoop $loop,
        AgentDefinition $definition,
        string $prompt,
    ): Generator {
        return (new TransientRunner(
            arena: $this->workspace->arena,
            ref: $branch === null ? 'main' : $this->ref($branch->name),
        ))->iterate($session, $loop, $definition, $prompt);
    }

    private function resolve(?string $requested): ResolvedBranch {
        return (new BranchResolver($this->workspace->arena, $this->workspace->branchSelection))
            ->resolve($requested);
    }

    private function resolved(TellBranchSelection $branch): ResolvedBranch {
        return new ResolvedBranch(
            $branch->name,
            $this->ref($branch->name),
            $branch->source === 'invocation',
        );
    }

    private function ref(string $branch): string {
        return $branch === 'main' ? 'main' : 'branches/' . $branch;
    }
}
