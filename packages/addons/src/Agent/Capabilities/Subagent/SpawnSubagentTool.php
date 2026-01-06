<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\Subagent;

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\Skills\SkillLibrary;
use Cognesy\Addons\Agent\Core\Collections\ToolExecutions;
use Cognesy\Addons\Agent\Core\Data\AgentExecution;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Enums\AgentStatus;
use Cognesy\Addons\Agent\Drivers\ToolCalling\ToolExecutionFormatter;
use Cognesy\Addons\Agent\Registry\AgentRegistry;
use Cognesy\Addons\Agent\Registry\AgentSpec;
use Cognesy\Addons\Agent\Tools\BaseTool;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;

class SpawnSubagentTool extends BaseTool
{
    private static array $subagentStates = [];

    private Agent $parentAgent;
    private AgentRegistry $registry;
    private ?SkillLibrary $skillLibrary;
    private ?LLMProvider $parentLlmProvider;
    private int $currentDepth;
    private int $maxDepth;
    private int $summaryMaxChars;
    private SubagentPolicy $policy;

    public function __construct(
        Agent $parentAgent,
        AgentRegistry $registry,
        ?SkillLibrary $skillLibrary = null,
        ?LLMProvider $parentLlmProvider = null,
        int $currentDepth = 0,
        int $maxDepth = 3,
        int $summaryMaxChars = 8000,
        ?SubagentPolicy $policy = null,
    ) {
        parent::__construct(
            name: 'spawn_subagent',
            description: $this->buildDescription($registry),
        );

        $this->parentAgent = $parentAgent;
        $this->registry = $registry;
        $this->skillLibrary = $skillLibrary;
        $this->parentLlmProvider = $parentLlmProvider;
        $this->policy = $policy ?? new SubagentPolicy(
            maxDepth: $maxDepth,
            summaryMaxChars: $summaryMaxChars,
        );
        $this->currentDepth = $currentDepth;
        $this->maxDepth = $this->policy->maxDepth;
        $this->summaryMaxChars = $this->policy->summaryMaxChars;
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
            $spec = $this->registry->get($subagentName);
        } catch (\Throwable $e) {
            return "[Subagent Error] {$e->getMessage()}";
        }

        // Create and run subagent
        $subagent = $this->createSubagent($spec);
        // Get parent's execution ID from injected agent state
        $parentExecutionId = $this->agentState?->agentId ?? 'unknown';
        $initialState = $this->createInitialState($prompt, $spec, $parentExecutionId);
        $finalState = $this->runSubagent($subagent, $initialState);

        // Store full state in metadata for external access (metrics, debugging)
        $this->storeSubagentState($finalState, $spec->name);

        // Return ONLY the response text to LLM (context isolation!)
        return $this->extractResponse($finalState, $spec->name);
    }

    // SUBAGENT CREATION ////////////////////////////////////////////

    private function createSubagent(AgentSpec $spec): Agent {
        // Filter tools based on spec
        $tools = $spec->filterTools($this->parentAgent->tools());

        // If spawn_subagent is in filtered tools, create nested version with incremented depth
        if ($tools->has('spawn_subagent')) {
            $nestedSpawnTool = new self(
                parentAgent: $this->parentAgent,
                registry: $this->registry,
                skillLibrary: $this->skillLibrary,
                parentLlmProvider: $this->parentLlmProvider,
                currentDepth: $this->currentDepth + 1,
                maxDepth: $this->maxDepth,
                summaryMaxChars: $this->summaryMaxChars,
                policy: $this->policy,
            );

            $tools = $tools->withToolRemoved('spawn_subagent')
                           ->withTool($nestedSpawnTool);
        }

        // Resolve LLM provider
        $llmProvider = $spec->resolveLlmProvider($this->parentLlmProvider);

        // Create agent (stateless blueprint)
        return AgentBuilder::new()
            ->withTools($tools)
            ->withDriver($this->parentAgent->driver()->withLLMProvider($llmProvider))
            ->build();
    }

    private function createInitialState(string $prompt, AgentSpec $spec, string $parentAgentId): AgentState {
        $messages = Messages::fromArray([
            ['role' => 'system', 'content' => $spec->systemPrompt],
        ]);

        $messages = $this->appendSkillMessages($messages, $spec);
        $messages = $messages->appendMessage(Message::asUser($prompt));

        return AgentState::empty()
            ->withMessages($messages)
            ->with(parentAgentId: $parentAgentId);
    }

    private function runSubagent(Agent $subagent, AgentState $state): AgentState {
        return $subagent->finalStep($state);
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
        if ($state->status() === AgentStatus::Failed) {
            $errorMsg = $state->currentStep()?->errorsAsString() ?? 'Unknown error';
            return "[Subagent: {$name}] Failed: {$errorMsg}";
        }

        $finalStep = $state->currentStep();
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

    private function appendSkillMessages(Messages $messages, AgentSpec $spec): Messages {
        if (!$spec->hasSkills() || $this->skillLibrary === null) {
            return $messages;
        }

        $executions = new ToolExecutions();
        foreach ($spec->skills ?? [] as $skillName) {
            $skill = $this->skillLibrary->getSkill($skillName);
            if ($skill === null) {
                continue;
            }

            $toolCallId = uniqid('load_skill_', true);
            $toolCall = new ToolCall('load_skill', ['skill_name' => $skillName], $toolCallId);
            $execution = new AgentExecution(
                toolCall: $toolCall,
                result: Result::success($skill->render()),
                startedAt: new DateTimeImmutable(),
                endedAt: new DateTimeImmutable(),
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
        $subagentNames = $this->registry->names();
        $descriptions = [];

        foreach ($this->registry->all() as $spec) {
            $tools = $spec->inheritsAllTools()
                ? 'all parent tools'
                : implode(', ', $spec->tools);

            $descriptions[] = "- {$spec->name}: {$spec->description} (tools: {$tools})";
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

    private function buildDescription(AgentRegistry $registry): string {
        $count = $registry->count();

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
