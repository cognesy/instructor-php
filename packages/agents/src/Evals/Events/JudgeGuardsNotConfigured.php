<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals\Events;

use Cognesy\Agents\Events\AgentEvent;

/**
 * Dispatched once per `AgentLoopJudge` instance when its built judge loop has
 * no `UseGuards` capability installed. This is a warning, not a failure -
 * `AgentLoopJudge` never substitutes its own step/token/time limits on the
 * developer's behalf. See "Make the judge an ordinary guarded agent" in
 * `00-current-state-and-decisions.md`.
 */
final class JudgeGuardsNotConfigured extends AgentEvent
{
    public function __construct(
        public readonly string $capability,
        public readonly string $suggestedFix,
    ) {
        parent::__construct([
            'capability' => $this->capability,
            'suggestedFix' => $this->suggestedFix,
        ]);
    }
}
