<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\PlanningSubagent;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\ExecutionBudget;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Tool\Tools\ContextAwareTool;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Contracts\CanAcceptLLMConfig;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
use RuntimeException;

final class PlanningSubagentTool extends ContextAwareTool
{
    public const string TOOL_NAME = 'plan_with_subagent';

    private Tools $parentTools;
    private CanUseTools $parentDriver;
    private string $plannerSystemPrompt;
    private ?NameList $plannerTools;
    private ExecutionBudget $plannerBudget;
    private CanHandleEvents $events;

    public function __construct(
        Tools $parentTools,
        CanUseTools $parentDriver,
        string $plannerSystemPrompt,
        ?NameList $plannerTools = null,
        ?ExecutionBudget $plannerBudget = null,
        ?CanHandleEvents $events = null,
    ) {
        parent::__construct(new PlanningSubagentToolDescriptor());

        $this->parentTools = $parentTools;
        $this->parentDriver = $parentDriver;
        $this->plannerSystemPrompt = $plannerSystemPrompt;
        $this->plannerTools = $plannerTools;
        $this->plannerBudget = $plannerBudget ?? ExecutionBudget::unlimited();
        $this->events = EventBusResolver::using($events);
    }

    #[\Override]
    public function withAgentState(AgentState $state): static {
        return $this->with(agentState: $state);
    }

    #[\Override]
    public function withToolCall(ToolCall $toolCall): static {
        return $this->with(toolCall: $toolCall);
    }

    #[\Override]
    public function __invoke(mixed ...$args): string
    {
        $specification = trim((string) $this->arg($args, 'specification', 0, ''));
        if ($specification === '') {
            return 'Error: specification is required';
        }

        $plannerLoop = $this->createPlannerLoop();
        $initialState = $this->createInitialState($specification);
        $finalState = $plannerLoop->execute($initialState);

        if ($finalState->status() === ExecutionStatus::Failed) {
            throw new RuntimeException($this->failureMessage($finalState));
        }

        return $this->extractPlan($finalState);
    }

    #[\Override]
    public function toToolSchema(): array
    {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string(
                        name: 'specification',
                        description: 'Task specification text with goal, context, expected outcomes, and acceptance criteria.',
                    ),
                ])
                ->withRequiredProperties(['specification'])
        )->toArray();
    }

    private function with(
        ?Tools $parentTools = null,
        ?CanUseTools $parentDriver = null,
        ?string $plannerSystemPrompt = null,
        ?NameList $plannerTools = null,
        ?ExecutionBudget $plannerBudget = null,
        ?CanHandleEvents $events = null,
        ?ToolCall $toolCall = null,
        ?AgentState $agentState = null,
    ): static {
        $new = new self(
            parentTools: $parentTools ?? $this->parentTools,
            parentDriver: $parentDriver ?? $this->parentDriver,
            plannerSystemPrompt: $plannerSystemPrompt ?? $this->plannerSystemPrompt,
            plannerTools: $plannerTools ?? $this->plannerTools,
            plannerBudget: $plannerBudget ?? $this->plannerBudget,
            events: $events ?? $this->events,
        );
        $new->toolCall = $toolCall ?? $this->toolCall;
        $new->agentState = $agentState ?? $this->agentState;
        return $new;
    }

    private function createPlannerLoop(): AgentLoop {
        $plannerTools = $this->filterPlannerTools($this->parentTools, $this->plannerTools);
        $driver = $this->resolveSubagentDriver($this->parentDriver);

        $builder = AgentBuilder::base($this->events)
            ->withCapability(new UseTools(...$plannerTools->all()))
            ->withCapability(new UseDriver($driver));

        return $this->applyBudget($builder)->build();
    }

    private function applyBudget(AgentBuilder $builder): AgentBuilder {
        if ($this->plannerBudget->isEmpty()) {
            return $builder;
        }

        return $builder->withCapability(new UseGuards(
            maxSteps: $this->plannerBudget->maxSteps,
            maxTokens: $this->plannerBudget->maxTokens,
            maxExecutionTime: $this->plannerBudget->maxSeconds,
        ));
    }

    private function resolveSubagentDriver(CanUseTools $driver): CanUseTools {
        if (!$driver instanceof CanAcceptLLMConfig) {
            return $driver;
        }

        $llmConfig = $this->agentState?->llmConfig() ?? LLMProvider::new()->resolveConfig();
        return $driver->withLLMConfig($llmConfig);
    }

    private function createInitialState(string $specification): AgentState {
        $state = AgentState::empty()->withMessages($this->plannerMessages($specification));
        if ($this->agentState === null) {
            return $state;
        }

        return $state
            ->with(parentAgentId: $this->agentState->agentId())
            ->withLLMConfig($this->agentState->llmConfig());
    }

    private function plannerMessages(string $specification): Messages {
        $systemPrompt = trim($this->plannerSystemPrompt);
        if ($systemPrompt === '') {
            return Messages::fromArray([
                ['role' => 'user', 'content' => $specification],
            ]);
        }

        return Messages::fromArray([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $specification],
        ]);
    }

    private function filterPlannerTools(Tools $parentTools, ?NameList $allowList): Tools {
        $plannerTools = match (true) {
            $allowList === null => $parentTools,
            $allowList->isEmpty() => $parentTools,
            default => $this->filterByAllowList($parentTools, $allowList),
        };

        return $this->removeRecursiveTools($plannerTools);
    }

    private function filterByAllowList(Tools $parentTools, NameList $allowList): Tools {
        $filtered = [];
        foreach ($parentTools->all() as $tool) {
            if ($allowList->has($tool->name())) {
                $filtered[] = $tool;
            }
        }

        return new Tools(...$filtered);
    }

    private function removeRecursiveTools(Tools $plannerTools): Tools {
        if ($plannerTools->isEmpty()) {
            return $plannerTools;
        }

        $filtered = [];
        foreach ($plannerTools->all() as $tool) {
            if ($tool->name() === self::TOOL_NAME) {
                continue;
            }
            if ($tool->name() === 'spawn_subagent') {
                continue;
            }
            $filtered[] = $tool;
        }

        return new Tools(...$filtered);
    }

    private function extractPlan(AgentState $state): string {
        $plan = trim($state->finalResponse()->toString());
        if ($plan !== '') {
            return $plan;
        }

        $fallback = trim($state->currentStepOrLast()?->outputMessages()->toString() ?? '');
        if ($fallback !== '') {
            return $fallback;
        }

        return 'Planning subagent produced no plan.';
    }

    private function failureMessage(AgentState $state): string {
        $details = trim($state->currentStepOrLast()?->errorsAsString() ?? '');
        return match (true) {
            $details === '' => 'Planning subagent execution failed.',
            default => "Planning subagent execution failed: {$details}",
        };
    }
}
