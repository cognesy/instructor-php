<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Tools\Subagent;

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentFactory;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Enums\AgentStatus;
use Cognesy\Addons\Agent\Skills\SkillLibrary;
use Cognesy\Addons\Agent\Subagents\SubagentRegistry;
use Cognesy\Addons\Agent\Subagents\SubagentSpec;
use Cognesy\Addons\Agent\Tools\BaseTool;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\LLMProvider;

class SpawnSubagentTool extends BaseTool
{
    private Agent $parentAgent;
    private SubagentRegistry $registry;
    private ?SkillLibrary $skillLibrary;
    private ?LLMProvider $parentLlmProvider;
    private int $currentDepth;
    private int $maxDepth;

    public function __construct(
        Agent $parentAgent,
        SubagentRegistry $registry,
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
    public function __invoke(mixed ...$args): string {
        $subagentName = $args['subagent'] ?? $args[0] ?? '';
        $prompt = $args['prompt'] ?? $args[1] ?? '';

        // Check depth limit
        if ($this->currentDepth >= $this->maxDepth) {
            return $this->formatError(
                "Maximum subagent nesting depth ({$this->maxDepth}) reached. " .
                "Cannot spawn '{$subagentName}' at depth {$this->currentDepth}."
            );
        }

        // Get subagent spec
        try {
            $spec = $this->registry->get($subagentName);
        } catch (\Throwable $e) {
            return $this->formatError($e->getMessage());
        }

        // Create and run subagent
        $subagent = $this->createSubagent($spec);
        $initialState = $this->createInitialState($prompt, $spec);
        $finalState = $this->runSubagent($subagent, $initialState);

        return $this->extractFinalResponse($finalState, $spec->name);
    }

    // SUBAGENT CREATION ////////////////////////////////////////////

    private function createSubagent(SubagentSpec $spec): Agent {
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

        // Create agent
        return AgentFactory::default(
            tools: $tools,
            llmPreset: null, // Don't use preset, use resolved provider directly
        )->with(
            driver: $this->parentAgent->driver()->withLLMProvider($llmProvider),
        );
    }

    private function createInitialState(string $prompt, SubagentSpec $spec): AgentState {
        $systemParts = [$spec->systemPrompt];

        // Preload skills into system prompt
        if ($spec->hasSkills() && $this->skillLibrary !== null) {
            $systemParts[] = "\n## Loaded Skills\n";

            foreach ($spec->skills as $skillName) {
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

        return AgentState::empty()->withMessages($messages);
    }

    private function runSubagent(Agent $subagent, AgentState $state): AgentState {
        return $subagent->finalStep($state);
    }

    // RESPONSE FORMATTING //////////////////////////////////////////

    private function extractFinalResponse(AgentState $state, string $name): string {
        $parts = ["[Subagent: {$name}]"];

        if ($state->status() === AgentStatus::Failed) {
            $errorMsg = $state->currentStep()?->errorsAsString() ?? 'Unknown error';
            $parts[] = "Status: Failed";
            $parts[] = "Error: {$errorMsg}";
            return implode("\n", $parts);
        }

        $parts[] = "Status: Completed";
        $parts[] = "Steps: {$state->stepCount()}";

        $finalStep = $state->currentStep();
        if ($finalStep !== null) {
            $response = $finalStep->outputMessages()->toString();
            if ($response !== '') {
                $parts[] = "";
                $parts[] = $response;
            }
        }

        return implode("\n", $parts);
    }

    private function formatError(string $message): string {
        return "[Subagent Error]\n{$message}";
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

    private function buildDescription(SubagentRegistry $registry): string {
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
