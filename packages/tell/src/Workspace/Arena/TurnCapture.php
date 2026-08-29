<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\ToolExecution;
use Cognesy\Messages\Message;
use Cognesy\Tell\Workspace\Arena\Record\Lineage;
use Cognesy\Tell\Workspace\Arena\Record\Message as RecordMessage;
use Cognesy\Tell\Workspace\Arena\Record\Role;
use Cognesy\Tell\Workspace\Arena\Record\TextPart;
use Cognesy\Tell\Workspace\Arena\Record\ToolCall;
use Cognesy\Tell\Workspace\Arena\Record\ToolResult;
use Cognesy\Tell\Workspace\Arena\Record\Turn;
use Cognesy\Tell\Workspace\Arena\Record\Value;
use Cognesy\Tell\Workspace\Execution\TurnException;
use JsonException;
use ValueError;

/**
 * Extracts the semantic conversation delta from a completed Agents execution.
 */
final class TurnCapture
{
    public function capture(
        AgentState $state,
        int $historyMessageCount,
        string $turnId,
        Lineage $lineage,
    ): Turn {
        $allMessages = $state->messages()->all();
        if (count($allMessages) < $historyMessageCount) {
            throw new TurnException('Tell workspace history changed during inference.');
        }

        [$toolCalls, $toolResults, $toolStepIds] = $this->toolTrace($state, $turnId);
        $messages = [];
        foreach (array_slice($allMessages, $historyMessageCount) as $message) {
            $stepId = $message->metadata()->get('step_id');
            if (is_string($stepId) && isset($toolStepIds[$stepId])) {
                continue;
            }
            $messages[] = $this->message($message);
        }

        return new Turn(
            id: $turnId,
            lineage: $lineage,
            messages: $messages,
            toolCalls: $toolCalls,
            toolResults: $toolResults,
        );
    }

    /**
     * @return array{0: list<ToolCall>, 1: list<ToolResult>, 2: array<string, true>}
     */
    private function toolTrace(AgentState $state, string $turnId): array {
        $calls = [];
        $results = [];
        $toolStepIds = [];
        $callNumber = 0;

        foreach ($state->stepExecutions()->all() as $stepExecution) {
            $step = $stepExecution->step();
            if (!$step->hasToolCalls()) {
                continue;
            }
            $toolStepIds[$step->id()->toString()] = true;
            $requested = $step->requestedToolCalls()->all();
            $executed = $step->toolExecutions()->all();
            if (count($requested) !== count($executed)) {
                throw new TurnException('Tell workspace cannot persist an incomplete tool trace.');
            }

            foreach ($requested as $index => $requestedCall) {
                $execution = $executed[$index] ?? null;
                if (!$execution instanceof ToolExecution || $execution->name() !== $requestedCall->name()) {
                    throw new TurnException('Tell workspace cannot pair a tool call with its result.');
                }
                $callNumber++;
                $callId = $turnId . '-tool-' . $callNumber;
                $calls[] = new ToolCall(
                    id: $callId,
                    name: $requestedCall->name(),
                    arguments: $requestedCall->arguments(),
                );
                $results[] = new ToolResult(
                    callId: $callId,
                    parts: [new TextPart($this->toolResultText($execution))],
                    isError: $execution->hasError(),
                );
            }
        }

        return [$calls, $results, $toolStepIds];
    }

    private function message(Message $message): RecordMessage {
        try {
            $role = Role::from($message->role()->value);
        } catch (ValueError) {
            throw new TurnException('Tell workspace only persists system, developer, user, and assistant messages.');
        }

        $parts = [];
        foreach ($message->contentParts()->all() as $part) {
            if (!$part->isTextPart() || !$part->hasText()) {
                throw new TurnException('Tell workspace only persists text message content.');
            }
            $parts[] = new TextPart($part->toString());
        }

        return new RecordMessage($role, $parts);
    }

    private function toolResultText(ToolExecution $execution): string {
        if ($execution->hasError()) {
            return $execution->errorMessage();
        }

        $value = Value::normalize($execution->value());
        if (is_string($value)) {
            return $value;
        }
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new TurnException('Tell workspace cannot persist this tool result.', previous: $exception);
        }
    }
}
