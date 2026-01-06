<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\SelfCritique;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Enums\AgentStepType;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Messages\Messages;

class SelfCriticProcessor implements CanProcessAnyState
{
    private const CRITIC_PROMPT = <<<'PROMPT'
You are a critical evaluator checking for factual errors and evidence contradictions.

## Original Task
%s

## Evidence Gathered (Tool Calls and Results)
%s

## Proposed Response
%s

## Evaluation Criteria

Compare the proposed response against the evidence gathered above.

**Check for:**
1. **Contradictions**: Does the response claim something that contradicts the evidence?
2. **Unsupported claims**: Does the response make claims without supporting evidence?
3. **Missed evidence**: Did the response overlook important data in the tool results?
4. **Incomplete investigation**: Are there obvious next steps that would strengthen the answer?

## Evaluation Rules

APPROVE if:
- The response directly answers the original task
- Claims are supported by the gathered evidence
- No contradictions between response and evidence

REJECT if:
- Response contradicts evidence shown above
- Response makes unsupported claims
- Critical evidence was overlooked
- An obvious investigation step was skipped

## Output Format

When rejecting, be specific:
- In weaknesses: State what the response claims vs what the evidence shows
- In suggestions: Name specific tool calls or files to check
PROMPT;

    private int $maxCriticIterations;
    private int $currentIteration = 0;
    private ?string $originalTask = null;
    private ?SelfCriticResult $lastResult = null;

    public function __construct(
        int $maxIterations = 2,
        private bool $verbose = true,
        private ?string $llmPreset = null,
    ) {
        $this->maxCriticIterations = $maxIterations;
    }

    public function lastResult(): ?SelfCriticResult {
        return $this->lastResult;
    }

    public function wasApproved(): bool {
        return $this->lastResult?->approved ?? false;
    }

    public function iterationCount(): int {
        return $this->currentIteration;
    }

    #[\Override]
    public function canProcess(object $state): bool {
        return $state instanceof AgentState;
    }

    #[\Override]
    public function process(object $state, ?callable $next = null): object {
        assert($state instanceof AgentState);

        // Capture original task from first message (before any processing)
        if ($this->originalTask === null && $state->messages()->count() > 0) {
            $firstMessage = $state->messages()->first();
            $this->originalTask = $firstMessage !== null ? $firstMessage->toString() : '';
        }

        // FIRST: Let the step execute by calling $next
        $newState = $next ? $next($state) : $state;
        assert($newState instanceof AgentState);

        // Now evaluate the NEW step that was just created
        $currentStep = $newState->currentStep();
        if ($currentStep === null) {
            return $newState;
        }

        $stepType = $currentStep->stepType();
        if ($this->verbose) {
            fwrite(STDERR, "  [SelfCritic] Checking step type: {$stepType->value}\n");
        }

        // Only critique final responses (not tool execution steps)
        if ($stepType !== AgentStepType::FinalResponse) {
            return $newState;
        }

        // Check current iteration from metadata
        $iteration = $newState->metadata()->get(SelfCriticContinuationCheck::ITERATION_KEY, 0);

        // Don't exceed max iterations
        if ($iteration >= $this->maxCriticIterations) {
            if ($this->verbose) {
                fwrite(STDERR, "  [SelfCritic] Max iterations ({$iteration}) reached, accepting response\n");
            }
            return $newState;
        }

        $response = $currentStep->outputMessages()->toString();
        if (empty($response)) {
            if ($this->verbose) {
                fwrite(STDERR, "  [SelfCritic] Response is empty, skipping\n");
            }
            return $newState;
        }

        // Run critic evaluation using Instructor for structured output
        if ($this->verbose) {
            $preview = substr($response, 0, 60);
            fwrite(STDERR, "  [SelfCritic] Evaluating: \"{$preview}...\"\n");
        }

        $evidence = $this->extractEvidence($newState);
        $result = $this->evaluateResponse($this->originalTask ?? '', $evidence, $response);
        $this->lastResult = $result;
        $this->currentIteration = $iteration + 1;

        if ($this->verbose) {
            $status = $result->approved ? '✓ APPROVED' : '✗ NEEDS IMPROVEMENT';
            fwrite(STDERR, "  [SelfCritic] {$status}: {$result->summary}\n");
        }

        // Store result in metadata for continuation check
        $newState = $newState
            ->withMetadata(SelfCriticContinuationCheck::METADATA_KEY, $result)
            ->withMetadata(SelfCriticContinuationCheck::ITERATION_KEY, $this->currentIteration);

        // If approved, return the state as-is
        if ($result->approved) {
            return $newState;
        }

        if ($this->verbose) {
            fwrite(STDERR, "  [SelfCritic] Requesting revision (iteration {$this->currentIteration}/{$this->maxCriticIterations})\n");
        }

        // Not approved - inject feedback so the agent continues
        $feedback = $result->toFeedback();
        $directive = !empty($result->suggestions)
            ? "Execute these tool calls to gather correct information, then provide a revised answer."
            : "Please investigate further and revise your response.";

        $feedbackMessage = Messages::fromArray([
            [
                'role' => 'user',
                'content' => "[CORRECTION REQUIRED - Iteration {$this->currentIteration}]\n\n" .
                    "**Problem:** {$result->summary}\n\n{$feedback}\n\n" .
                    "**Action:** {$directive}",
            ],
        ]);

        // Add the current response + feedback to messages so agent can improve
        return $newState->withMessages(
            $newState->messages()->appendMessages(
                $currentStep->outputMessages()
            )->appendMessages($feedbackMessage)
        );
    }

    /**
     * Extract evidence from agent's tool execution history.
     */
    private function extractEvidence(AgentState $state): string {
        $evidence = [];

        foreach ($state->steps() as $step) {
            // Include tool calls and their results
            if ($step->hasToolCalls()) {
                foreach ($step->toolCalls()->all() as $toolCall) {
                    $evidence[] = "Tool: {$toolCall->name()}";
                    $args = $toolCall->args();
                    if (!empty($args)) {
                        $argStr = json_encode($args, JSON_UNESCAPED_SLASHES);
                        $evidence[] = "Args: {$argStr}";
                    }
                }
            }

            // Include tool execution results
            if ($step->toolExecutions()->hasExecutions()) {
                foreach ($step->toolExecutions()->all() as $execution) {
                    $value = $execution->value();
                    if (is_string($value)) {
                        $truncated = $this->truncateContent($value);
                        $evidence[] = "Result ({$execution->name()}):\n{$truncated}";
                    }
                }
            }
        }

        return empty($evidence) ? '(No tool calls made)' : implode("\n\n", $evidence);
    }

    /**
     * Truncate long content while preserving beginning and end.
     */
    private function truncateContent(string $content, int $maxLength = 6000): string {
        if (strlen($content) <= $maxLength) {
            return $content;
        }

        $halfLength = (int)($maxLength / 2);
        $omitted = strlen($content) - $maxLength;

        return substr($content, 0, $halfLength) .
            "\n\n... [{$omitted} characters omitted] ...\n\n" .
            substr($content, -$halfLength);
    }

    private function evaluateResponse(string $task, string $evidence, string $response): SelfCriticResult {
        $prompt = sprintf(self::CRITIC_PROMPT, $task, $evidence, $response);

        try {
            $structured = (new StructuredOutput())
                ->withMessages($prompt)
                ->withResponseClass(SelfCriticResult::class)
                ->withMaxRetries(2);

            if ($this->llmPreset !== null) {
                $structured = $structured->using($this->llmPreset);
            }

            /** @var SelfCriticResult */
            return $structured->get();
        } catch (\Throwable $e) {
            // On failure, approve to avoid blocking
            if ($this->verbose) {
                fwrite(STDERR, "  [SelfCritic] Evaluation failed: {$e->getMessage()}\n");
            }
            return new SelfCriticResult(
                approved: true,
                summary: 'Critic evaluation failed, approving by default.',
            );
        }
    }
}
