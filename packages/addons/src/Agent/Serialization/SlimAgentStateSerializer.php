<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Serialization;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Data\AgentStep;
use Cognesy\Polyglot\Inference\Data\ToolCall;

final readonly class SlimAgentStateSerializer implements CanSerializeAgentState
{
    public function __construct(
        private SlimSerializationConfig $config = new SlimSerializationConfig(),
    ) {}

    #[\Override]
    public function serialize(AgentState $state): array {
        $steps = [];
        if ($this->config->includeSteps) {
            $steps = $this->serializeSteps($state);
        }

        $continuation = null;
        if ($this->config->includeContinuationTrace) {
            $continuation = $this->serializeContinuation($state);
        }

        return [
            'agent_id' => $state->agentId,
            'parent_agent_id' => $state->parentAgentId,
            'status' => $state->status()->value,
            'step_count' => $state->stepCount(),
            'usage' => $state->usage()->toArray(),
            'execution' => $this->serializeExecution($state),
            'messages' => $this->serializeMessages($state),
            'steps' => $steps,
            'last_continuation' => $continuation,
            'metadata' => $state->metadata()->toArray(),
        ];
    }

    #[\Override]
    public function deserialize(array $data): AgentState {
        $messages = $data['messages'] ?? [];
        $execution = $data['execution'] ?? [];

        return AgentState::fromArray([
            'agentId' => $data['agent_id'] ?? null,
            'parentAgentId' => $data['parent_agent_id'] ?? null,
            'status' => $data['status'] ?? null,
            'usage' => $data['usage'] ?? [],
            'metadata' => $data['metadata'] ?? [],
            'stateInfo' => [
                'startedAt' => $execution['started_at'] ?? null,
                'updatedAt' => $execution['updated_at'] ?? null,
                'cumulativeExecutionSeconds' => $execution['cumulative_seconds'] ?? 0,
            ],
            'messageStore' => [
                'sections' => [
                    [
                        'name' => 'messages',
                        'messages' => is_array($messages) ? $messages : [],
                    ],
                ],
            ],
        ]);
    }

    private function serializeMessages(AgentState $state): array {
        $messages = $state->messages()->toArray();

        if ($this->config->maxMessages <= 0) {
            return [];
        }

        if (count($messages) > $this->config->maxMessages) {
            $messages = array_slice($messages, -$this->config->maxMessages);
        }

        return array_map(
            fn(array $message): array => $this->serializeMessage($message),
            $messages,
        );
    }

    private function serializeMessage(array $message): array {
        $content = $message['content'] ?? '';
        if (is_string($content) && $this->config->maxContentLength > 0) {
            if (strlen($content) > $this->config->maxContentLength) {
                $content = substr($content, 0, $this->config->maxContentLength) . '...';
            }
        }

        $metadata = $message['_metadata'] ?? $message['metadata'] ?? [];
        if ($this->config->redactToolArgs) {
            $metadata = $this->redactToolArgs($metadata);
        }

        if (!$this->config->includeToolResults && ($message['role'] ?? '') === 'tool') {
            $content = '[tool result omitted]';
        }

        return [
            'role' => $message['role'] ?? 'user',
            'content' => $content,
            '_metadata' => $metadata,
        ];
    }

    private function redactToolArgs(array $metadata): array {
        if (!isset($metadata['tool_calls']) || !is_array($metadata['tool_calls'])) {
            return $metadata;
        }

        $metadata['tool_calls'] = array_map(
            fn(array $toolCall): array => [
                'id' => $toolCall['id'] ?? null,
                'name' => $this->resolveToolName($toolCall),
            ],
            $metadata['tool_calls'],
        );

        return $metadata;
    }

    private function resolveToolName(array $toolCall): string {
        if (isset($toolCall['name']) && is_string($toolCall['name'])) {
            return $toolCall['name'];
        }

        $function = $toolCall['function'] ?? null;
        if (is_array($function) && isset($function['name']) && is_string($function['name'])) {
            return $function['name'];
        }

        return 'unknown';
    }

    private function serializeExecution(AgentState $state): array {
        $stateInfo = $state->stateInfo();
        return [
            'started_at' => $stateInfo->startedAt()->format(DATE_ATOM),
            'updated_at' => $stateInfo->updatedAt()->format(DATE_ATOM),
            'cumulative_seconds' => $stateInfo->cumulativeExecutionSeconds(),
        ];
    }

    private function serializeSteps(AgentState $state): array {
        $steps = $state->steps()->all();
        if ($this->config->maxSteps <= 0) {
            return [];
        }

        if (count($steps) > $this->config->maxSteps) {
            $steps = array_slice($steps, -$this->config->maxSteps);
        }

        return array_map(
            fn(AgentStep $step, int $index): array => $this->serializeStep($step, $index),
            $steps,
            array_keys($steps),
        );
    }

    private function serializeStep(AgentStep $step, int $index): array {
        return [
            'step_number' => $index + 1,
            'type' => $step->stepType()->value,
            'has_tool_calls' => $step->hasToolCalls(),
            'finish_reason' => $step->finishReason()?->value,
            'errors' => count($step->errors()),
            'usage' => ['total' => $step->usage()->total()],
            'tool_calls' => array_map(
                fn(ToolCall $toolCall): array => [
                    'id' => $toolCall->id(),
                    'name' => $toolCall->name(),
                ],
                $step->toolCalls()->all(),
            ),
        ];
    }

    private function serializeContinuation(AgentState $state): ?array {
        // @phpstan-ignore function.alreadyNarrowedType (forward-compatibility check)
        if (!method_exists($state, 'lastContinuationOutcome')) {
            return null;
        }

        $outcome = $state->lastContinuationOutcome();
        if ($outcome === null) {
            return null;
        }

        return [
            'should_continue' => $outcome->shouldContinue(),
            'stop_reason' => $outcome->stopReason()->value,
            'resolved_by' => $outcome->resolvedBy(),
        ];
    }
}
