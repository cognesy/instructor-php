<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Subagent;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Skills\SkillLibrary;
use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinition;
use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Core\Enums\ExecutionStatus;
use Cognesy\Agents\Core\Tools\BaseTool;
use Cognesy\Agents\Drivers\ToolCalling\ToolExecutionFormatter;
use Cognesy\Agents\Events\AgentEventEmitter;
use Cognesy\Agents\Events\CanAcceptAgentEventEmitter;
use Cognesy\Agents\Events\CanEmitAgentEvents;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;

class SpawnSubagentTool extends BaseTool
{
    private static array $subagentStates = [];

    private Tools $parentTools;
    private CanUseTools $parentDriver;
    private AgentDefinitionProvider $provider;
    private ?SkillLibrary $skillLibrary;
    private ?LLMProvider $parentLlmProvider;
    private int $currentDepth;
    private int $maxDepth;
    private int $summaryMaxChars;
    private SubagentPolicy $policy;
    private CanEmitAgentEvents $eventEmitter;

    public function __construct(
        Tools $parentTools,
        CanUseTools $parentDriver,
        AgentDefinitionProvider $provider,
        ?SkillLibrary $skillLibrary = null,
        ?LLMProvider $parentLlmProvider = null,
        int $currentDepth = 0,
        int $maxDepth = 3,
        int $summaryMaxChars = 8000,
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
        $this->policy = $policy ?? new SubagentPolicy(
            maxDepth: $maxDepth,
            summaryMaxChars: $summaryMaxChars,
        );
        $this->currentDepth = $currentDepth;
        $this->maxDepth = $this->policy->maxDepth;
        $this->summaryMaxChars = $this->policy->summaryMaxChars;
        $this->eventEmitter = $eventEmitter ?? new AgentEventEmitter();
    }

    #[\Override]
    public function __invoke(mixed ...$args): mixed {
        $subagentName = $args['subagent'] ?? $args[0] ?? '';
        $prompt = $args['prompt'] ?? $args[1] ?? '';

        // Check depth limit
        if ($this->currentDepth >= $this->maxDepth) {
            return "[Subagent Error] Maximum nesting depth ({$this->maxDepth}) reached for '{$subagentName}'.";
        }

        // Get subagent spec
        try {
            $spec = $this->provider->get($subagentName);
        } catch (\Throwable $e) {
            return "[Subagent Error] {$e->getMessage()}";
        }

        // Get parent's execution ID from injected agent state
        $parentExecutionId = $this->agentState?->agentId() ?? 'unknown';

        // Emit subagent spawning event
        $spawnStartedAt = new DateTimeImmutable();
        $this->eventEmitter->subagentSpawning(
            parentAgentId: $parentExecutionId,
            subagentName: $subagentName,
            prompt: $prompt,
            depth: $this->currentDepth,
            maxDepth: $this->maxDepth,
        );

        // Create and run subagent loop
        $subagentLoop = $this->createSubagentLoop($spec);
        $initialState = $this->createInitialState($prompt, $spec, $parentExecutionId);
        $finalState = $this->runSubagentLoop($subagentLoop, $initialState);

        // Emit subagent completed event
        $this->eventEmitter->subagentCompleted(
            parentAgentId: $parentExecutionId,
            subagentId: $finalState->agentId(),
            subagentName: $subagentName,
            status: $finalState->status(),
            steps: $finalState->stepCount(),
            usage: $finalState->usage(),
            startedAt: $spawnStartedAt,
        );

        // Store full state in metadata for external access (metrics, debugging)
        $this->storeSubagentState($finalState, $spec->name);

        // Return ONLY the response text to LLM (context isolation!)
        return $this->extractResponse($finalState, $spec->name);
    }

    // SUBAGENT CREATION ////////////////////////////////////////////

    private function createSubagentLoop(AgentDefinition $spec): AgentLoop {
        // Filter tools based on spec
        $tools = $spec->filterTools($this->parentTools);

        // If spawn_subagent is in filtered tools, create nested version with incremented depth
        if ($tools->has('spawn_subagent')) {
            $nestedSpawnTool = new self(
                parentTools: $this->parentTools,
                parentDriver: $this->parentDriver,
                provider: $this->provider,
                skillLibrary: $this->skillLibrary,
                parentLlmProvider: $this->parentLlmProvider,
                currentDepth: $this->currentDepth + 1,
                maxDepth: $this->maxDepth,
                summaryMaxChars: $this->summaryMaxChars,
                policy: $this->policy,
                eventEmitter: $this->eventEmitter,
            );

            $tools = $tools->withToolRemoved('spawn_subagent')
                           ->withTool($nestedSpawnTool);
        }

        // Resolve LLM provider
        $llmProvider = $spec->resolveLlmProvider($this->parentLlmProvider);

        // Get the parent's driver with the new LLM provider, preserving event emitter
        $subagentDriver = $this->parentDriver->withLLMProvider($llmProvider);
        if ($subagentDriver instanceof CanAcceptAgentEventEmitter) {
            $subagentDriver = $subagentDriver->withEventEmitter($this->eventEmitter);
        }

        // Create agent loop (stateless blueprint) - share event bus with parent
        return AgentBuilder::new()
            ->withTools($tools)
            ->withDriver($subagentDriver)
            ->withEvents($this->eventEmitter->eventHandler())
            ->build();
    }

    private function createInitialState(string $prompt, AgentDefinition $spec, string $parentAgentId): AgentState {
        $messages = Messages::fromArray([
            ['role' => 'system', 'content' => $spec->systemPrompt],
        ]);

        $messages = $this->appendSkillMessages($messages, $spec);
        $messages = $messages->appendMessage(Message::asUser($prompt));

        return AgentState::empty()
            ->withMessages($messages)
            ->with(parentAgentId: $parentAgentId);
    }

    private function runSubagentLoop(AgentLoop $subagentLoop, AgentState $state): AgentState {
        return $subagentLoop->execute($state);
    }

    // STATE STORAGE & EXTRACTION ///////////////////////////////////

    private function storeSubagentState(AgentState $state, string $name): void {
        self::$subagentStates[] = [
            'name' => $name,
            'state' => $state,
            'timestamp' => time(),
        ];
    }

    public static function getSubagentStates(): array {
        return self::$subagentStates;
    }

    public static function clearSubagentStates(): void {
        self::$subagentStates = [];
    }

    private function extractResponse(AgentState $state, string $name): string {
        if ($state->status() === ExecutionStatus::Failed) {
            $errorMsg = $state->currentStepOrLast()?->errorsAsString() ?? 'Unknown error';
            return "[Subagent: {$name}] Failed: {$errorMsg}";
        }

        $finalStep = $state->currentStepOrLast();
        $response = $finalStep?->outputMessages()->toString() ?? '';

        if ($response === '') {
            return "[Subagent: {$name}] No response";
        }

        $summary = $this->summarizeResponse($response);
        return "[Subagent: {$name}] {$summary}";
    }

    private function summarizeResponse(string $response): string {
        $response = trim($response);
        if ($response === '') {
            return '(no response)';
        }

        if ($this->summaryMaxChars <= 0 || strlen($response) <= $this->summaryMaxChars) {
            return $response;
        }

        $snippet = substr($response, 0, $this->summaryMaxChars);
        return rtrim($snippet) . "\n...";
    }

    private function appendSkillMessages(Messages $messages, AgentDefinition $spec): Messages {
        if (!$spec->hasSkills() || $this->skillLibrary === null) {
            return $messages;
        }

        $skillNames = $spec->skills;
        if ($skillNames === null || $skillNames->isEmpty()) {
            return $messages;
        }

        $executions = new ToolExecutions();
        foreach ($skillNames->all() as $skillName) {
            $skill = $this->skillLibrary->getSkill($skillName);
            if ($skill === null) {
                continue;
            }

            $toolCallId = uniqid('load_skill_', true);
            $toolCall = new ToolCall('load_skill', ['skill_name' => $skillName], $toolCallId);
            $execution = new ToolExecution(
                toolCall: $toolCall,
                result: Result::success($skill->render()),
                startedAt: new DateTimeImmutable(),
                completedAt: new DateTimeImmutable(),
            );
            $executions = $executions->withAddedExecution($execution);
        }

        if (!$executions->hasExecutions()) {
            return $messages;
        }

        $skillMessages = (new ToolExecutionFormatter())->makeExecutionMessages($executions);
        return $messages->appendMessages($skillMessages);
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
        if (!empty($descriptions)) {
            $descriptionText .= "\n\nAvailable subagents:\n" . implode("\n", $descriptions);
        }

        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $descriptionText,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'subagent' => [
                            'type' => 'string',
                            'enum' => $subagentNames,
                            'description' => 'Which subagent to spawn',
                        ],
                        'prompt' => [
                            'type' => 'string',
                            'description' => 'The task or question for the subagent',
                        ],
                    ],
                    'required' => ['subagent', 'prompt'],
                ],
            ],
        ];
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
