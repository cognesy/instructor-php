<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\ToolExecution;
use Cognesy\Messages\Message;
use Cognesy\Tell\Canonical\CanonicalLineage;
use Cognesy\Tell\Canonical\CanonicalMessage;
use Cognesy\Tell\Canonical\CanonicalRole;
use Cognesy\Tell\Canonical\CanonicalTextPart;
use Cognesy\Tell\Canonical\CanonicalToolCall;
use Cognesy\Tell\Canonical\CanonicalToolResult;
use Cognesy\Tell\Canonical\CanonicalTurn;
use Cognesy\Tell\Canonical\CanonicalValue;
use JsonException;

/**
 * Extracts the semantic conversation delta from a completed Agents execution.
 */
final class CanonicalTurnCapture
{
    public function capture(
        AgentState $state,
        int $historyMessageCount,
        string $turnId,
        CanonicalLineage $lineage,
    ): CanonicalTurn {
        $allMessages = $state->messages()->all();
        if (count($allMessages) < $historyMessageCount) {
            throw new WorkspaceTurnException('Tell workspace history changed during inference.');
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

        return new CanonicalTurn(
            id: $turnId,
            lineage: $lineage,
            messages: $messages,
            toolCalls: $toolCalls,
            toolResults: $toolResults,
        );
    }

    /**
     * @return array{0: list<CanonicalToolCall>, 1: list<CanonicalToolResult>, 2: array<string, true>}
     */
    private function toolTrace(AgentState $state, string $turnId): array
    {
        $calls = [];
        $results = [];
        $toolStepIds = [];
        $callNumber = 0;

        foreach ($state->stepExecutions()->all() as $stepExecution) {
            $step = $stepExecution->step();
            if (! $step->hasToolCalls()) {
                continue;
            }
            $toolStepIds[$step->id()->toString()] = true;
            $requested = $step->requestedToolCalls()->all();
            $executed = $step->toolExecutions()->all();
            if (count($requested) !== count($executed)) {
                throw new WorkspaceTurnException('Tell workspace cannot persist an incomplete tool trace.');
            }

            foreach ($requested as $index => $requestedCall) {
                $execution = $executed[$index] ?? null;
                if (! $execution instanceof ToolExecution || $execution->name() !== $requestedCall->name()) {
                    throw new WorkspaceTurnException('Tell workspace cannot pair a tool call with its result.');
                }
                $callNumber++;
                $callId = $turnId.'-tool-'.$callNumber;
                $calls[] = new CanonicalToolCall(
                    id: $callId,
                    name: $requestedCall->name(),
                    arguments: $requestedCall->arguments(),
                );
                $results[] = new CanonicalToolResult(
                    callId: $callId,
                    parts: [new CanonicalTextPart($this->toolResultText($execution))],
                    isError: $execution->hasError(),
                );
            }
        }

        return [$calls, $results, $toolStepIds];
    }

    private function message(Message $message): CanonicalMessage
    {
        try {
            $role = CanonicalRole::from($message->role()->value);
        } catch (\ValueError) {
            throw new WorkspaceTurnException('Tell workspace only persists system, developer, user, and assistant messages.');
        }

        $parts = [];
        foreach ($message->contentParts()->all() as $part) {
            if (! $part->isTextPart() || ! $part->hasText()) {
                throw new WorkspaceTurnException('Tell workspace only persists text message content.');
            }
            $parts[] = new CanonicalTextPart($part->toString());
        }

        return new CanonicalMessage($role, $parts);
    }

    private function toolResultText(ToolExecution $execution): string
    {
        if ($execution->hasError()) {
            return $execution->errorMessage();
        }

        $value = CanonicalValue::normalize($execution->value());
        if (is_string($value)) {
            return $value;
        }
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new WorkspaceTurnException('Tell workspace cannot persist this tool result.', previous: $exception);
        }
    }
}
