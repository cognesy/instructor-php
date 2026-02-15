<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Subagent;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Capability\Skills\SkillLibrary;
use Cognesy\Agents\Capability\Subagent\Exceptions\SubagentDepthExceededException;
use Cognesy\Agents\Capability\Subagent\Exceptions\SubagentExecutionException;
use Cognesy\Agents\Capability\Subagent\Exceptions\SubagentNotFoundException;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentBudget;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Events\SubagentCompleted;
use Cognesy\Agents\Events\SubagentSpawning;
use Cognesy\Agents\Template\Contracts\CanManageAgentDefinitions;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Tool\Tools\ContextAwareTool;
use Cognesy\Events\Contracts\CanAcceptEventHandler;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanAcceptLLMProvider;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
use DateTimeImmutable;

final class SpawnSubagentTool extends ContextAwareTool
{
    private Tools $parentTools;
    private CanUseTools $parentDriver;
    private CanManageAgentDefinitions $provider;
    private ?SkillLibrary $skillLibrary;
    private ?LLMProvider $parentLlmProvider;
    private int $currentDepth;
    private SubagentPolicy $policy;
    private CanHandleEvents $events;

    public function __construct(
        Tools $parentTools,
        CanUseTools $parentDriver,
        CanManageAgentDefinitions $provider,
        ?SkillLibrary $skillLibrary = null,
        ?LLMProvider $parentLlmProvider = null,
        int $currentDepth = 0,
        ?SubagentPolicy $policy = null,
        ?CanHandleEvents $events = null,
    ) {
        parent::__construct(new SpawnSubagentToolDescriptor($provider));

        $this->parentTools = $parentTools;
        $this->parentDriver = $parentDriver;
        $this->provider = $provider;
        $this->skillLibrary = $skillLibrary;
        $this->parentLlmProvider = $parentLlmProvider;
        $this->policy = $policy ?? SubagentPolicy::default();
        $this->currentDepth = $currentDepth;
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

    private function with(
        ?Tools $parentTools = null,
        ?CanUseTools $parentDriver = null,
        ?CanManageAgentDefinitions $provider = null,
        ?SkillLibrary $skillLibrary = null,
        ?LLMProvider $parentLlmProvider = null,
        ?int $currentDepth = null,
        ?SubagentPolicy $policy = null,
        ?CanHandleEvents $events = null,
        ?ToolCall $toolCall = null,
        ?AgentState $agentState = null,
    ): static {
        $new = new self(
            parentTools: $parentTools ?? $this->parentTools,
            parentDriver: $parentDriver ?? $this->parentDriver,
            provider: $provider ?? $this->provider,
            skillLibrary: $skillLibrary ?? $this->skillLibrary,
            parentLlmProvider: $parentLlmProvider ?? $this->parentLlmProvider,
            currentDepth: $currentDepth ?? $this->currentDepth,
            policy: $policy ?? $this->policy,
            events: $events ?? $this->events,
        );
        $new->toolCall = $toolCall ?? $this->toolCall;
        $new->agentState = $agentState ?? $this->agentState;
        return $new;
    }

    #[\Override]
    public function __invoke(mixed ...$args): mixed {
        $subagentName = $this->arg($args, 'subagent', 0, '');
        $prompt = $this->arg($args, 'prompt', 1, '');

        if ($this->currentDepth >= $this->policy->maxDepth) {
            throw new SubagentDepthExceededException(
                subagentName: $subagentName,
                currentDepth: $this->currentDepth,
                maxDepth: $this->policy->maxDepth,
            );
        }

        try {
            $spec = $this->provider->get($subagentName);
        } catch (\Throwable $e) {
            throw new SubagentNotFoundException($subagentName, $e);
        }

        // Extract correlation IDs for tracing
        $parentAgentId = $this->agentState?->agentId() ?? 'unknown';
        $parentExecutionId = $this->agentState?->execution()?->executionId();
        $parentStepNumber = $this->agentState?->stepCount();
        $toolCallId = $this->toolCall?->id();

        $spawnStartedAt = new DateTimeImmutable();
        $this->emitSubagentSpawning(
            parentAgentId: $parentAgentId,
            subagentName: $subagentName,
            prompt: $prompt,
            depth: $this->currentDepth,
            maxDepth: $this->policy->maxDepth,
            parentExecutionId: $parentExecutionId,
            parentStepNumber: $parentStepNumber,
            toolCallId: $toolCallId,
        );

        $subagentLoop = $this->createSubagentLoop($spec);
        $initialState = $this->createInitialState($prompt, $spec, $parentAgentId);
        $finalState = $subagentLoop->execute($initialState);

        $this->emitSubagentCompleted(
            parentAgentId: $parentAgentId,
            subagentId: $finalState->agentId(),
            subagentName: $subagentName,
            status: $finalState->status(),
            steps: $finalState->stepCount(),
            usage: $finalState->usage(),
            startedAt: $spawnStartedAt,
            parentExecutionId: $parentExecutionId,
            parentStepNumber: $parentStepNumber,
            toolCallId: $toolCallId,
        );

        if ($finalState->status() === ExecutionStatus::Failed) {
            throw SubagentExecutionException::fromState($finalState, $spec->name);
        }

        return $finalState;
    }

    // SUBAGENT CREATION ////////////////////////////////////////////

    private function createSubagentLoop(AgentDefinition $spec): AgentLoop {
        $tools = $this->filterTools($spec, $this->parentTools);

        if ($tools->has('spawn_subagent')) {
            $nestedSpawnTool = new self(
                parentTools: $this->parentTools,
                parentDriver: $this->parentDriver,
                provider: $this->provider,
                skillLibrary: $this->skillLibrary,
                parentLlmProvider: $this->parentLlmProvider,
                currentDepth: $this->currentDepth + 1,
                policy: $this->policy,
                events: $this->events,
            );

            $tools = $tools->withToolRemoved('spawn_subagent')
                           ->withTool($nestedSpawnTool);
        }

        $subagentDriver = $this->parentDriver;

        if ($subagentDriver instanceof CanAcceptLLMProvider) {
            $llmProvider = $this->resolveLlmProvider($spec, $this->parentLlmProvider);
            $subagentDriver = $subagentDriver->withLLMProvider($llmProvider);
        }

        if ($subagentDriver instanceof CanAcceptEventHandler) {
            $subagentDriver = $subagentDriver->withEventHandler($this->events);
        }

        // Compute effective budget: parent's remaining capped by definition limits
        $effectiveBudget = $this->computeEffectiveBudget($spec);

        $builder = AgentBuilder::base($this->events)
            ->withCapability(new UseTools(...$tools->all()))
            ->withCapability(new UseDriver($subagentDriver));

        $builder = $this->applyBudgetConstraints($builder, $effectiveBudget);

        return $builder->build();
    }

    private function computeEffectiveBudget(AgentDefinition $spec): AgentBudget {
        $parentBudget = $this->agentState?->budget() ?? AgentBudget::unlimited();
        $parentExecution = $this->agentState?->execution();

        $remainingBudget = match ($parentExecution) {
            null => $parentBudget,
            default => $parentBudget->remainingFrom($parentExecution),
        };

        $definitionBudget = $spec->budget();

        return $remainingBudget->cappedBy($definitionBudget);
    }

    private function applyBudgetConstraints(AgentBuilder $builder, AgentBudget $budget): AgentBuilder {
        return $builder->withCapability(new UseGuards(
            maxSteps: $budget->maxSteps,
            maxTokens: $budget->maxTokens,
            maxExecutionTime: $budget->maxSeconds,
        ));
    }

    private function createInitialState(string $prompt, AgentDefinition $spec, string $parentAgentId): AgentState {
        $messages = Messages::fromArray([
            ['role' => 'system', 'content' => $spec->systemPrompt],
        ]);

        $messages = $this->appendSkillMessages($messages, $spec);
        $messages = $messages->appendMessage(Message::asUser($prompt));

        $effectiveBudget = $this->computeEffectiveBudget($spec);

        return AgentState::empty()
            ->withMessages($messages)
            ->withBudget($effectiveBudget)
            ->with(parentAgentId: $parentAgentId);
    }

    // SKILL INJECTION //////////////////////////////////////////////

    private function appendSkillMessages(Messages $messages, AgentDefinition $spec): Messages {
        if (!$spec->hasSkills() || $this->skillLibrary === null) {
            return $messages;
        }

        $skillContent = [];
        foreach ($spec->skills->all() as $skillName) {
            $skill = $this->skillLibrary->getSkill($skillName);
            if ($skill !== null) {
                $skillContent[] = $skill->render();
            }
        }

        if ($skillContent === []) {
            return $messages;
        }

        return $messages->appendMessage(
            new Message(role: 'system', content: implode("\n\n", $skillContent))
        );
    }

    // TOOL SCHEMA //////////////////////////////////////////////////

    #[\Override]
    public function toToolSchema(): array {
        $subagentNames = $this->provider->names();
        $descriptions = [];

        foreach ($this->provider->all() as $spec) {
            $toolsDescription = 'all parent tools';
            if (!$spec->inheritsAllTools()) {
                $toolsDescription = implode(', ', $spec->tools?->all() ?? []);
            }

            $descriptions[] = "- {$spec->name}: {$spec->description} (tools: {$toolsDescription})";
        }

        $descriptionText = $this->description();
        if ($descriptions !== []) {
            $descriptionText .= "\n\nAvailable subagents:\n" . implode("\n", $descriptions);
        }

        return ToolSchema::make(
            name: $this->name(),
            description: $descriptionText,
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::enum('subagent', $subagentNames, 'Which subagent to spawn'),
                    JsonSchema::string('prompt', 'The task or question for the subagent'),
                ])
                ->withRequiredProperties(['subagent', 'prompt'])
        )->toArray();
    }

    // SUBAGENT ASSEMBLY ////////////////////////////////////////////

    private function filterTools(AgentDefinition $spec, Tools $parentTools): Tools {
        $tools = match($spec->inheritsAllTools()) {
            true => $parentTools,
            false => $this->filterByAllowList($parentTools, $spec),
        };

        return $this->filterByDenyList($tools, $spec);
    }

    private function filterByAllowList(Tools $parentTools, AgentDefinition $spec): Tools {
        $filtered = [];
        foreach ($parentTools->all() as $tool) {
            if ($spec->tools?->has($tool->name()) ?? false) {
                $filtered[] = $tool;
            }
        }

        return new Tools(...$filtered);
    }

    private function filterByDenyList(Tools $tools, AgentDefinition $spec): Tools {
        if ($spec->toolsDeny === null || $spec->toolsDeny->isEmpty()) {
            return $tools;
        }

        $filtered = [];
        foreach ($tools->all() as $tool) {
            if (!$spec->toolsDeny->has($tool->name())) {
                $filtered[] = $tool;
            }
        }

        return new Tools(...$filtered);
    }

    private function resolveLlmProvider(AgentDefinition $spec, ?LLMProvider $parentProvider): LLMProvider {
        return match (true) {
            $spec->llmConfig instanceof LLMConfig => LLMProvider::new()->withConfig($spec->llmConfig),
            $spec->llmConfig === null => $parentProvider ?? LLMProvider::new(),
            is_string($spec->llmConfig) => LLMProvider::using($spec->llmConfig),
            default => LLMProvider::new(),
        };
    }

    // HELPERS //////////////////////////////////////////////////////

    // EVENT EMISSION ////////////////////////////////////////////

    private function emitSubagentSpawning(
        string $parentAgentId,
        string $subagentName,
        string $prompt,
        int $depth,
        int $maxDepth,
        ?string $parentExecutionId,
        ?int $parentStepNumber,
        ?string $toolCallId,
    ): void {
        $this->events->dispatch(new SubagentSpawning(
            parentAgentId: $parentAgentId,
            subagentName: $subagentName,
            prompt: $prompt,
            depth: $depth,
            maxDepth: $maxDepth,
            parentExecutionId: $parentExecutionId,
            parentStepNumber: $parentStepNumber,
            toolCallId: $toolCallId,
        ));
    }

    private function emitSubagentCompleted(
        string $parentAgentId,
        string $subagentId,
        string $subagentName,
        ExecutionStatus $status,
        int $steps,
        ?\Cognesy\Polyglot\Inference\Data\Usage $usage,
        DateTimeImmutable $startedAt,
        ?string $parentExecutionId,
        ?int $parentStepNumber,
        ?string $toolCallId,
    ): void {
        $this->events->dispatch(new SubagentCompleted(
            parentAgentId: $parentAgentId,
            subagentId: $subagentId,
            subagentName: $subagentName,
            status: $status,
            steps: $steps,
            usage: $usage,
            startedAt: $startedAt,
            parentExecutionId: $parentExecutionId,
            parentStepNumber: $parentStepNumber,
            toolCallId: $toolCallId,
        ));
    }
}
