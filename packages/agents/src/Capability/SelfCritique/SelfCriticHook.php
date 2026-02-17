<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\SelfCritique;

use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Enums\AgentStepType;
use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Messages\Messages;

final class SelfCriticHook implements HookInterface
{
    public const string METADATA_KEY = 'self_critic_result';
    public const string ITERATION_KEY = 'self_critic_iteration';
    private const string ORIGINAL_TASK_KEY = 'self_critic_original_task';

    private const string CRITIC_PROMPT = <<<'PROMPT'
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

    public function __construct(
        private CanCreateStructuredOutput $structuredOutput,
        private int $maxCriticIterations = 2,
    ) {}

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $state = $context->state();
        $currentStep = $state->currentStep();

        if ($currentStep === null || $currentStep->stepType() !== AgentStepType::FinalResponse) {
            return $context;
        }

        $state = $this->ensureOriginalTaskCaptured($state);
        $iteration = $state->metadata()->get(self::ITERATION_KEY, 0);
        $response = $currentStep->outputMessages()->toString();

        if ($response === '') {
            return $context->withState(
                $this->applyContinuationEvaluation($state, null, $iteration)
            );
        }

        if ($iteration >= $this->maxCriticIterations) {
            $result = new SelfCriticResult(approved: false, summary: 'Max iterations reached');
            $state = $state
                ->withMetadata(self::METADATA_KEY, $result)
                ->withMetadata(self::ITERATION_KEY, $iteration);
            return $context->withState(
                $this->applyContinuationEvaluation($state, $result, $iteration)
            );
        }

        return $this->evaluateAndApply($context, $state, $iteration, $response);
    }

    // PRIVATE //////////////////////////////////////////////////////

    private function evaluateAndApply(
        HookContext $context,
        AgentState $state,
        int $iteration,
        string $response,
    ): HookContext {
        $originalTask = $state->metadata()->get(self::ORIGINAL_TASK_KEY, '');
        $evidence = $this->extractEvidence($state);
        $result = $this->evaluateResponse($originalTask, $evidence, $response);
        $newIteration = $iteration + 1;

        $state = $state
            ->withMetadata(self::METADATA_KEY, $result)
            ->withMetadata(self::ITERATION_KEY, $newIteration);

        $state = $this->applyContinuationEvaluation($state, $result, $newIteration);

        return match(true) {
            $result->approved => $context->withState($state),
            default => $context->withState($this->injectFeedback($state, $result, $newIteration)),
        };
    }

    private function ensureOriginalTaskCaptured(AgentState $state): AgentState
    {
        if ($state->metadata()->get(self::ORIGINAL_TASK_KEY) !== null) {
            return $state;
        }
        $firstMessage = $state->messages()->first();
        $task = $firstMessage->toString();
        return $state->withMetadata(self::ORIGINAL_TASK_KEY, $task);
    }

    private function injectFeedback(AgentState $state, SelfCriticResult $result, int $iteration): AgentState
    {
        $feedback = $result->toFeedback();
        $directive = match(true) {
            $result->suggestions !== [] => 'Execute these tool calls to gather correct information, then provide a revised answer.',
            default => 'Please investigate further and revise your response.',
        };

        $feedbackMessage = Messages::fromArray([[
            'role' => 'user',
            'content' => "[CORRECTION REQUIRED - Iteration {$iteration}]\n\n"
                . "**Problem:** {$result->summary}\n\n{$feedback}\n\n"
                . "**Action:** {$directive}",
        ]]);

        $currentStep = $state->currentStep();
        return $state->withMessages(
            $state->messages()
                ->appendMessages($currentStep->outputMessages())
                ->appendMessages($feedbackMessage)
        );
    }

    private function applyContinuationEvaluation(
        AgentState $state,
        ?SelfCriticResult $result,
        int $iteration,
    ): AgentState {
        return match(true) {
            $result === null => $state,
            $result->approved => $state,
            $iteration >= $this->maxCriticIterations => $state->withStopSignal(new StopSignal(
                reason: StopReason::RetryLimitReached,
                message: sprintf('Max self-critic iterations reached: %d/%d', $iteration, $this->maxCriticIterations),
                context: ['iteration' => $iteration, 'maxIterations' => $this->maxCriticIterations],
                source: self::class,
            )),
            default => $state->withExecutionContinued(),
        };
    }

    private function extractEvidence(AgentState $state): string
    {
        $evidence = [];

        foreach ($state->steps() as $step) {
            if ($step->hasToolCalls()) {
                foreach ($step->requestedToolCalls()->all() as $toolCall) {
                    $evidence[] = "Tool: {$toolCall->name()}";
                    $args = $toolCall->args();
                    if ($args !== []) {
                        $evidence[] = "Args: " . json_encode($args, JSON_UNESCAPED_SLASHES);
                    }
                }
            }

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

        return $evidence === [] ? '(No tool calls made)' : implode("\n\n", $evidence);
    }

    private function truncateContent(string $content, int $maxLength = 6000): string
    {
        if (strlen($content) <= $maxLength) {
            return $content;
        }

        $halfLength = (int)($maxLength / 2);
        $omitted = strlen($content) - $maxLength;

        return substr($content, 0, $halfLength)
            . "\n\n... [{$omitted} characters omitted] ...\n\n"
            . substr($content, -$halfLength);
    }

    private function evaluateResponse(string $task, string $evidence, string $response): SelfCriticResult
    {
        $prompt = sprintf(self::CRITIC_PROMPT, $task, $evidence, $response);
        $request = new StructuredOutputRequest(
            messages: $prompt,
            requestedSchema: SelfCriticResult::class,
        );

        try {
            /** @var SelfCriticResult */
            return $this->structuredOutput->create($request)->get();
        } catch (\Throwable) {
            return new SelfCriticResult(
                approved: true,
                summary: 'Critic evaluation failed, approving by default.',
            );
        }
    }
}
