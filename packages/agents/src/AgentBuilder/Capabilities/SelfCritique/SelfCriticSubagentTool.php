<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\SelfCritique;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Enums\ExecutionStatus;
use Cognesy\Agents\Core\Tools\BaseTool;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Messages\Messages;

class SelfCriticSubagentTool extends BaseTool
{
    private const string CRITIC_PROMPT = <<<'PROMPT'
You are a critical evaluator. Your job is to assess whether a proposed response adequately addresses the original task.

## Original Task
%s

## Proposed Response
%s

## Your Evaluation

Analyze the response against these criteria:
1. **Completeness**: Does the response fully address all aspects of the task?
2. **Accuracy**: Is the information correct and well-supported?
3. **Relevance**: Does the response stay focused on what was asked?
4. **Quality**: Is the response clear, well-structured, and useful?

Provide your evaluation in this format:

APPROVED: [YES/NO]

STRENGTHS:
- List what the response does well

WEAKNESSES:
- List what is missing, incorrect, or could be improved

SUGGESTIONS:
- Specific actionable improvements if not approved

Be rigorous but fair. Only approve responses that genuinely address the task well.
PROMPT;

    public function __construct() {
        parent::__construct(
            name: 'self_critic',
            description: 'Evaluate if your response adequately addresses the original task. Use before finalizing to ensure quality. Returns approval status and improvement suggestions.',
        );
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $originalTask = (string) $this->arg($args, 'original_task', 0, '');
        $proposedResponse = (string) $this->arg($args, 'proposed_response', 1, '');

        if ($originalTask === '') {
            return "Error: original_task is required";
        }
        if ($proposedResponse === '') {
            return "Error: proposed_response is required";
        }

        $prompt = sprintf(self::CRITIC_PROMPT, $originalTask, $proposedResponse);

        // Create minimal subagent with no tools - pure evaluation
        $subagent = AgentBuilder::base()->withTools(new Tools())->build();

        $subState = AgentState::empty()->withMessages(
            Messages::fromString($prompt)
        );

        // Run critic subagent
        $finalState = $subagent->execute($subState);

        if ($finalState->status() === ExecutionStatus::Failed) {
            $error = $finalState->currentStep()?->errorsAsString() ?? 'Unknown error';
            return "Critic evaluation failed: {$error}";
        }

        $evaluation = $finalState->currentStep()?->outputMessages()->toString() ?? '';

        return $this->formatEvaluation($evaluation);
    }

    private function formatEvaluation(string $evaluation): string {
        // Extract approval status for easy parsing
        $approved = str_contains(strtoupper($evaluation), 'APPROVED: YES');

        $status = $approved ? '✓ APPROVED' : '✗ NEEDS IMPROVEMENT';

        return "[Self-Critic Evaluation]\nStatus: {$status}\n\n{$evaluation}";
    }

    #[\Override]
    public function toToolSchema(): array {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('original_task', 'The original task or question that needs to be addressed'),
                    JsonSchema::string('proposed_response', 'Your proposed response to evaluate before finalizing'),
                ])
                ->withRequiredProperties(['original_task', 'proposed_response'])
        )->toArray();
    }
}
