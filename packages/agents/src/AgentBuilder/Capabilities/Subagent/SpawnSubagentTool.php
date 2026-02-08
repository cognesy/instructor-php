<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Subagent;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Skills\SkillLibrary;
use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\Exceptions\SubagentDepthExceededException;
use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\Exceptions\SubagentExecutionException;
use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\Exceptions\SubagentNotFoundException;
use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinition;
use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanAcceptLLMProvider;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\Budget;
use Cognesy\Agents\Core\Enums\ExecutionStatus;
use Cognesy\Agents\Core\Tools\BaseTool;
use Cognesy\Agents\Core\Tools\CanAccessToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
use Cognesy\Agents\Events\AgentEventEmitter;
use Cognesy\Agents\Events\CanAcceptAgentEventEmitter;
use Cognesy\Agents\Events\CanEmitAgentEvents;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\LLMProvider;
use DateTimeImmutable;

final class SpawnSubagentTool extends BaseTool implements CanAccessToolCall
{
    private Tools $parentTools;
    private CanUseTools $parentDriver;
    private AgentDefinitionProvider $provider;
    private ?SkillLibrary $skillLibrary;
    private ?LLMProvider $parentLlmProvider;
    private int $currentDepth;
    private SubagentPolicy $policy;
    private CanEmitAgentEvents $eventEmitter;
    private ?ToolCall $toolCall = null;

    public function __construct(
        Tools $parentTools,
        CanUseTools $parentDriver,
        AgentDefinitionProvider $provider,
        ?SkillLibrary $skillLibrary = null,
        ?LLMProvider $parentLlmProvider = null,
        int $currentDepth = 0,
        ?SubagentPolicy $policy = null,
        ?CanEmitAgentEvents $eventEmitter = null,
    ) {
        parent::__construct(
            name: 'spawn_subagent',
            description: $this->buildDescription($provider),
        );

        $this->parentTools = $parentTools;
        $this->parentDriver = $parentDriver;
        $this->provider = $provider;
        $this->skillLibrary = $skillLibrary;
        $this->parentLlmProvider = $parentLlmProvider;
        $this->policy = $policy ?? SubagentPolicy::default();
        $this->currentDepth = $currentDepth;
        $this->eventEmitter = $eventEmitter ?? new AgentEventEmitter();
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
        ?AgentDefinitionProvider $provider = null,
        ?SkillLibrary $skillLibrary = null,
        ?LLMProvider $parentLlmProvider = null,
        ?int $currentDepth = null,
        ?SubagentPolicy $policy = null,
        ?CanEmitAgentEvents $eventEmitter = null,
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
            eventEmitter: $eventEmitter ?? $this->eventEmitter,
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
        $this->eventEmitter->subagentSpawning(
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

        $this->eventEmitter->subagentCompleted(
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
                eventEmitter: $this->eventEmitter,
            );

            $tools = $tools->withToolRemoved('spawn_subagent')
                           ->withTool($nestedSpawnTool);
        }

        $subagentDriver = $this->parentDriver;

        if ($subagentDriver instanceof CanAcceptLLMProvider) {
            $llmProvider = $this->resolveLlmProvider($spec, $this->parentLlmProvider);
            $subagentDriver = $subagentDriver->withLLMProvider($llmProvider);
        }

        if ($subagentDriver instanceof CanAcceptAgentEventEmitter) {
            $subagentDriver = $subagentDriver->withEventEmitter($this->eventEmitter);
        }

        // Compute effective budget: parent's remaining capped by definition limits
        $effectiveBudget = $this->computeEffectiveBudget($spec);

        $builder = AgentBuilder::base()
            ->withTools($tools)
            ->withDriver($subagentDriver)
            ->withEvents($this->eventEmitter->eventHandler());

        $builder = $this->applyBudgetConstraints($builder, $effectiveBudget);

        return $builder->build();
    }

    private function computeEffectiveBudget(AgentDefinition $spec): Budget {
        // Start with parent's remaining budget
        $parentBudget = $this->agentState?->budget() ?? Budget::unlimited();
        $parentExecution = $this->agentState?->execution();

        $remainingBudget = match ($parentExecution) {
            null => $parentBudget,
            default => $parentBudget->remainingFrom($parentExecution),
        };

        // Cap with definition's limits
        $definitionBudget = Budget::fromDefinition(
            maxSteps: $spec->maxSteps,
            maxTokens: $spec->maxTokens,
            timeoutSec: $spec->timeoutSec,
        );

        return $remainingBudget->cappedBy($definitionBudget);
    }

    private function applyBudgetConstraints(AgentBuilder $builder, Budget $budget): AgentBuilder {
        if ($budget->maxSteps !== null) {
            $builder = $builder->withMaxSteps($budget->maxSteps);
        }

        if ($budget->maxTokens !== null) {
            $builder = $builder->withMaxTokens($budget->maxTokens);
        }

        if ($budget->maxSeconds !== null) {
            $builder = $builder->withTimeout((int) ceil($budget->maxSeconds));
        }

        return $builder;
    }

    private function createInitialState(string $prompt, AgentDefinition $spec, string $parentAgentId): AgentState {
        $messages = Messages::fromArray([
            ['role' => 'system', 'content' => $spec->systemPrompt],
        ]);

        $messages = $this->appendSkillMessages($messages, $spec);
        $messages = $messages->appendMessage(Message::asUser($prompt));

        // Pass effective budget to child so it can propagate further
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

        $descriptionText = $this->description;
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

    private function buildDescription(AgentDefinitionProvider $provider): string {
        $count = $provider->count();

        if ($count === 0) {
            return 'Spawn an isolated subagent for a focused task. No subagents are currently registered.';
        }

        return <<<DESC
Spawn a specialized subagent for a focused task. Returns only the final response.

Each subagent has specific expertise, tools, and capabilities optimized for its domain.
Choose the most appropriate subagent based on the task requirements.
DESC;
    }
}
