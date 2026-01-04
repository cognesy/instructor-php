<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Tools\Subagent;

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentFactory;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Enums\AgentStatus;
use Cognesy\Addons\Agent\Enums\AgentType;
use Cognesy\Addons\Agent\Subagents\AgentCapability;
use Cognesy\Addons\Agent\Subagents\DefaultAgentCapability;
use Cognesy\Addons\Agent\Tools\BaseTool;
use Cognesy\Messages\Messages;

class SpawnSubagentTool extends BaseTool
{
    private Agent $parentAgent;
    private AgentCapability $capability;

    public function __construct(
        Agent $parentAgent,
        ?AgentCapability $capability = null,
    ) {
        parent::__construct(
            name: 'spawn_subagent',
            description: <<<'DESC'
Spawn an isolated subagent for a focused task. Returns only the final response.

Examples:
- description="Find auth implementation", prompt="Where is user authentication handled?", agent_type="explore"
- description="Fix login bug", prompt="The login form throws error X. Find and fix it.", agent_type="code"
- description="Design caching strategy", prompt="How should we implement Redis caching?", agent_type="plan"

Agent types:
- explore: Read-only analysis (search_files, read_file, list_dir)
- code: Full access (includes edit_file, write_file, bash)
- plan: Design only (no file modifications)
DESC,
        );

        $this->parentAgent = $parentAgent;
        $this->capability = $capability ?? new DefaultAgentCapability();
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $description = $args['description'] ?? $args[0] ?? '';
        $prompt = $args['prompt'] ?? $args[1] ?? '';
        $agent_type = $args['agent_type'] ?? $args[2] ?? 'explore';

        $type = $this->parseAgentType($agent_type);
        $filteredTools = $this->capability->toolsFor($type, $this->parentAgent->tools());
        $systemPromptAddition = $this->capability->systemPromptFor($type);

        $subagent = $this->createSubagent($filteredTools);
        $initialState = $this->createInitialState($prompt, $systemPromptAddition);

        $finalState = $this->runSubagent($subagent, $initialState);

        return $this->extractFinalResponse($finalState, $description);
    }

    private function parseAgentType(string $type): AgentType {
        return match(strtolower(trim($type))) {
            'explore', 'exploration' => AgentType::Explore,
            'code', 'coding' => AgentType::Code,
            'plan', 'planning' => AgentType::Plan,
            default => AgentType::Explore,
        };
    }

    private function createSubagent(Tools $tools): Agent {
        return AgentFactory::default(tools: $tools);
    }

    private function createInitialState(string $prompt, string $systemPromptAddition): AgentState {
        $systemMessage = $systemPromptAddition;
        $userMessage = $prompt;

        $messages = Messages::fromArray([
            ['role' => 'system', 'content' => $systemMessage],
            ['role' => 'user', 'content' => $userMessage],
        ]);

        return AgentState::empty()->withMessages($messages);
    }

    private function runSubagent(Agent $subagent, AgentState $state): AgentState {
        $finalState = $state;

        foreach ($subagent->iterator($state) as $stepState) {
            $finalState = $stepState;
        }

        return $finalState;
    }

    private function extractFinalResponse(AgentState $state, string $description): string {
        $parts = ["[Subagent: {$description}]"];

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

    #[\Override]
    public function toToolSchema(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'description' => [
                            'type' => 'string',
                            'description' => 'Short description of what the subagent will do',
                        ],
                        'prompt' => [
                            'type' => 'string',
                            'description' => 'The task or question for the subagent',
                        ],
                        'agent_type' => [
                            'type' => 'string',
                            'enum' => ['explore', 'code', 'plan'],
                            'description' => 'Type of subagent: explore (read-only), code (full access), plan (design only)',
                        ],
                    ],
                    'required' => ['description', 'prompt'],
                ],
            ],
        ];
    }
}
