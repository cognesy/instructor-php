<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\PlanningSubagent;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Builder\Contracts\CanProvideDeferredTools;
use Cognesy\Agents\Builder\Data\DeferredToolContext;
use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\ExecutionBudget;
use Cognesy\Agents\Hook\Collections\HookTriggers;

final class UsePlanningSubagent implements CanProvideAgentCapability
{
    private const string DEFAULT_PARENT_INSTRUCTIONS = <<<'PROMPT'
For complex tasks, use the `plan_with_subagent` tool before implementation.

When calling `plan_with_subagent`, provide a text task specification with these sections:
- Goal
- Context
- Expected Outcomes
- Acceptance Criteria

Add constraints and non-goals if relevant. After receiving the plan, continue execution using the plan as guidance.
PROMPT;

    private const string DEFAULT_PLANNER_SYSTEM_PROMPT = <<<'PROMPT'
You are a planning specialist.

Your job is to create an implementation plan for the provided task specification.
You can use available tools to gather additional context before finalizing the plan.
Do not implement changes. Return only a dense markdown plan.

Respect required sections and constraints provided in the task specification.
PROMPT;

    public function __construct(
        private string $parentInstructions = self::DEFAULT_PARENT_INSTRUCTIONS,
        private string $plannerSystemPrompt = self::DEFAULT_PLANNER_SYSTEM_PROMPT,
        private ?NameList $plannerTools = null,
        private ?ExecutionBudget $plannerBudget = null,
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_planning_subagent';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $agent = $this->configurePromptInstructions($agent);
        return $this->configurePlanningTool($agent);
    }

    private function configurePromptInstructions(CanConfigureAgent $agent): CanConfigureAgent {
        $instructions = trim($this->parentInstructions);
        if ($instructions === '') {
            return $agent;
        }

        $hooks = $agent->hooks()->with(
            hook: new PlanningSubagentInstructionsHook($instructions),
            triggerTypes: HookTriggers::beforeStep(),
            priority: 90,
            name: 'planning_subagent:instructions',
        );
        return $agent->withHooks($hooks);
    }

    private function configurePlanningTool(CanConfigureAgent $agent): CanConfigureAgent {
        $plannerSystemPrompt = $this->plannerSystemPrompt;
        $plannerTools = $this->plannerTools;
        $plannerBudget = $this->plannerBudget ?? ExecutionBudget::unlimited();

        $deferred = new class($plannerSystemPrompt, $plannerTools, $plannerBudget) implements CanProvideDeferredTools {
            public function __construct(
                private string $plannerSystemPrompt,
                private ?NameList $plannerTools,
                private ExecutionBudget $plannerBudget,
            ) {}

            #[\Override]
            public function provideTools(DeferredToolContext $context): Tools {
                return new Tools(new PlanningSubagentTool(
                    parentTools: $context->tools(),
                    parentDriver: $context->toolUseDriver(),
                    plannerSystemPrompt: $this->plannerSystemPrompt,
                    plannerTools: $this->plannerTools,
                    plannerBudget: $this->plannerBudget,
                    events: $context->events(),
                ));
            }
        };

        return $agent->withDeferredTools(
            $agent->deferredTools()->withProvider($deferred)
        );
    }
}
