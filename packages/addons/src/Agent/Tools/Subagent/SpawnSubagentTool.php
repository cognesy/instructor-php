<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Tools\Subagent;

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Enums\AgentStatus;
use Cognesy\Addons\Agent\Skills\SkillLibrary;
use Cognesy\Addons\Agent\Agents\AgentRegistry;
use Cognesy\Addons\Agent\Agents\AgentSpec;
use Cognesy\Addons\Agent\Tools\BaseTool;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\LLMProvider;

class SpawnSubagentTool extends BaseTool
{
    private static array $subagentStates = [];

    private Agent $parentAgent;
    private AgentRegistry $registry;
    private ?SkillLibrary $skillLibrary;
    private ?LLMProvider $parentLlmProvider;
    private int $currentDepth;
    private int $maxDepth;

    public function __construct(
        Agent $parentAgent,
        AgentRegistry $registry,
        ?SkillLibrary $skillLibrary = null,
        ?LLMProvider $parentLlmProvider = null,
        int $currentDepth = 0,
        int $maxDepth = 3,
    ) {
        parent::__construct(
            name: 'spawn_subagent',
            description: $this->buildDescription($registry),
        );

        $this->parentAgent = $parentAgent;
        $this->registry = $registry;
        $this->skillLibrary = $skillLibrary;
        $this->parentLlmProvider = $parentLlmProvider;
        $this->currentDepth = $currentDepth;
        $this->maxDepth = $maxDepth;
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
        $systemParts = [$spec->systemPrompt];

        // Preload skills into system prompt
        if ($spec->hasSkills() && $this->skillLibrary !== null) {
            $systemParts[] = "\n## Loaded Skills\n";

            foreach ($spec->skills ?? [] as $skillName) {
                $skill = $this->skillLibrary->getSkill($skillName);
                if ($skill !== null) {
                    $systemParts[] = $skill->render();
                    $systemParts[] = ""; // Blank line between skills
                }
            }
        }

        $systemMessage = implode("\n", $systemParts);

        $messages = Messages::fromArray([
            ['role' => 'system', 'content' => $systemMessage],
            ['role' => 'user', 'content' => $prompt],
        ]);

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

        // Extract only first line to ensure context isolation
        $lines = explode("\n", trim($response));
        $firstLine = trim($lines[0]);

        // Remove markdown formatting if present
        $firstLine = preg_replace('/^\*\*|\*\*$/', '', $firstLine) ?? $firstLine;
        $firstLine = preg_replace('/^#+\s*/', '', $firstLine) ?? $firstLine;

        return "[Subagent: {$name}] {$firstLine}";
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
