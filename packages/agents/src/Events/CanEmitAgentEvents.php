<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Core\Enums\ExecutionStatus;
use Cognesy\Agents\Core\Stop\StopSignal;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;
use Throwable;

/**
 * Emits agent lifecycle events.
 */
interface CanEmitAgentEvents
{
    // Core lifecycle events
    public function executionStarted(AgentState $state, int $availableTools): void;
    public function stepStarted(AgentState $state): void;
    public function stepCompleted(AgentState $state): void;
    public function stateUpdated(AgentState $state): void;
    public function continuationEvaluated(AgentState $state): void;
    public function executionStopped(AgentState $state): void;
    public function executionFinished(AgentState $state): void;
    public function executionFailed(AgentState $state, Throwable $exception): void;

    // Tool events
    public function toolCallStarted(ToolCall $toolCall, DateTimeImmutable $startedAt): void;
    public function toolCallCompleted(ToolExecution $execution): void;
    public function toolCallBlocked(ToolCall $toolCall, string $reason, ?string $hookName = null): void;

    // Inference events
    public function inferenceRequestStarted(AgentState $state, int $messageCount, ?string $model = null): void;
    public function inferenceResponseReceived(AgentState $state, ?InferenceResponse $response, DateTimeImmutable $requestStartedAt): void;

    // Subagent events
    public function subagentSpawning(string $parentAgentId, string $subagentName, string $prompt, int $depth, int $maxDepth): void;
    public function subagentCompleted(string $parentAgentId, string $subagentId, string $subagentName, ExecutionStatus $status, int $steps, ?Usage $usage, DateTimeImmutable $startedAt): void;

    // Hook events
    public function hookExecuted(string $hookType, string $tool, string $outcome, ?string $reason, DateTimeImmutable $startedAt): void;

    // Extraction/Validation events
    public function decisionExtractionFailed(AgentState $state, string $errorMessage, string $errorType, int $attemptNumber = 1, int $maxAttempts = 1): void;
    public function validationFailed(AgentState $state, string $validationType, array $errors): void;
    public function stopSignalReceived(StopSignal $signal);
}
