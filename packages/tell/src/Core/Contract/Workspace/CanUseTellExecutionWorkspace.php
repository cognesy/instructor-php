<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Workspace;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Tell\Core\Contract\Agent\CanDescribeTellDelegation;
use Cognesy\Tell\Data\TellBranchSelection;
use Generator;

/** Backend-neutral execution view of one opened Tell workspace. */
interface CanUseTellExecutionWorkspace
{
    public function root(): string;

    public function branch(?string $requested): TellBranchSelection;

    public function delegation(
        TellBranchSelection $branch,
        ?CanProvideCancellationSignal $cancellation = null,
    ): CanDescribeTellDelegation;

    /** @return Generator<int, AgentState, mixed, AgentState> */
    public function turn(
        TellBranchSelection $branch,
        AgentLoop $loop,
        AgentDefinition $definition,
        string $prompt,
    ): Generator;

    /** @return Generator<int, AgentState, mixed, AgentState> */
    public function session(
        SessionId $session,
        AgentLoop $loop,
        AgentDefinition $definition,
        string $prompt,
    ): Generator;

    /** @return Generator<int, AgentState, mixed, AgentState> */
    public function transient(
        ?SessionId $session,
        ?TellBranchSelection $branch,
        AgentLoop $loop,
        AgentDefinition $definition,
        string $prompt,
    ): Generator;
}
