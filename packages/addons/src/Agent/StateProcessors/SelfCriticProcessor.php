<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\StateProcessors;

use Cognesy\Addons\Agent\Continuation\SelfCriticContinuationCheck;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Data\SelfCriticResult;
use Cognesy\Addons\Agent\Enums\AgentStepType;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Messages\Messages;

/**
 * Automatically evaluates final responses against the original task.
 * If the critic disapproves, injects feedback into messages so the agent
 * can iterate and improve its response.
 *
 * This processor acts as POST-STEP middleware: it lets the step execute first,
 * then evaluates the result and potentially requests a revision.
 *
 * @implements CanProcessAnyState<AgentState>
 */
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

## CRITICAL: Check Evidence Quality and Contradictions

Compare the proposed response against the ACTUAL evidence gathered above.

**Evidence Quality Checks:**
- For "what framework/library is used" questions: Did the agent check composer.json or package.json? Config files alone (phpunit.xml, jest.config) are NOT authoritative - the actual dependencies in composer.json/package.json are.
- Was the evidence from authoritative sources or just circumstantial?

**Contradiction Checks:**
- Response claims X but tool results clearly showed Y
- Response ignores key data visible in the evidence
- Response makes assumptions instead of using gathered evidence

Example: Finding `phpunit.xml` does NOT prove PHPUnit - Pest also uses phpunit.xml. Must check composer.json require-dev to see actual testing package installed.

## Evaluation Rules

APPROVE only if:
- Evidence is from authoritative sources (composer.json, package.json, not just config files)
- The conclusion matches ALL evidence gathered above
- No contradictions between claims and tool results

REJECT if you find:
1. **Weak evidence**: Conclusion based on config files without checking composer.json/package.json
2. **Evidence contradiction**: Response ignores data shown in tool results above
3. **Wrong conclusion**: Evidence clearly points to X but response says Y
4. **Missed evidence**: Tool results contain the answer but response missed it

## Required Output When Rejecting

In weaknesses, be specific about the gap:
- BAD: "Response may be incomplete"
- GOOD: "Response concludes 'PHPUnit' based on phpunit.xml, but composer.json require-dev was not checked"
- GOOD: "Response says 'PHPUnit' but evidence shows 'pestphp/pest' in composer.json"

In suggestions, give specific tool calls:
- BAD: "Check more files"
- GOOD: "read_file: composer.json - check require-dev section for actual testing package"
- GOOD: "search_files: *composer.json - find and read composer.json to verify testing framework"
PROMPT;

    private int $maxCriticIterations;
    private int $currentIteration = 0;
    private ?string $originalTask = null;
    private ?SelfCriticResult $lastResult = null;

    public function __construct(
        private int $maxIterations = 2,
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
            $this->originalTask = $state->messages()->first()?->toString() ?? '';
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
                        $truncated = $this->smartTruncate($value, $execution->name());
                        $evidence[] = "Result ({$execution->name()}):\n{$truncated}";
                    }
                }
            }
        }

        return empty($evidence) ? '(No tool calls made)' : implode("\n\n", $evidence);
    }

    /**
     * Smart truncation that preserves important sections.
     * For JSON files like composer.json, keeps require-dev section visible.
     */
    private function smartTruncate(string $content, string $toolName): string {
        $maxLength = 8000; // Increased limit for better evidence

        if (strlen($content) <= $maxLength) {
            return $content;
        }

        // For composer.json or package.json, extract key sections
        if (str_contains($toolName, 'read_file') || str_contains($toolName, 'read')) {
            // Try to find and preserve require-dev section (PHP) or devDependencies (JS)
            if (preg_match('/"require-dev"\s*:\s*\{[^}]+\}/s', $content, $matches)) {
                $requireDev = $matches[0];
                $header = substr($content, 0, 500);
                return $header . "\n\n... [truncated middle section] ...\n\n" . $requireDev . "\n\n... [truncated remainder]";
            }
            if (preg_match('/"devDependencies"\s*:\s*\{[^}]+\}/s', $content, $matches)) {
                $devDeps = $matches[0];
                $header = substr($content, 0, 500);
                return $header . "\n\n... [truncated middle section] ...\n\n" . $devDeps . "\n\n... [truncated remainder]";
            }
        }

        // Default: keep first and last portions
        $halfLength = (int)($maxLength / 2);
        return substr($content, 0, $halfLength) .
            "\n\n... [truncated " . (strlen($content) - $maxLength) . " characters] ...\n\n" .
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
