<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Messages\ToolResult;
use Cognesy\Tell\Canonical\CanonicalConversationRoot;
use Cognesy\Tell\Canonical\CanonicalHash;
use Cognesy\Tell\Canonical\CanonicalMessage;
use Cognesy\Tell\Canonical\CanonicalToolCall;
use Cognesy\Tell\Canonical\CanonicalToolResult;
use Cognesy\Tell\Canonical\CanonicalTurn;
use Cognesy\Tell\Canonical\CanonicalTurnStatus;

/**
 * Compiles canonical ancestry into the message/tool transcript accepted by Agents.
 *
 * Provider configuration remains outside this compiler: its output is determined
 * exclusively by immutable arena records.
 */
final class ArenaHistoryCompiler
{
    public function compile(ArenaStore $store, ?CanonicalHash $head): ArenaHistory
    {
        if ($head === null) {
            return new ArenaHistory(null, null, null, Messages::empty(), []);
        }

        $headRecord = $store->get($head);
        if ($headRecord instanceof CanonicalConversationRoot) {
            return new ArenaHistory(
                referenceHead: $head,
                turnHead: null,
                root: $head,
                messages: $this->messagesFromCanonical($headRecord->messages()),
                turns: [],
            );
        }

        $turns = [];
        $seen = [];
        $cursor = $head;
        $root = null;
        while ($cursor !== null) {
            $value = $cursor->toString();
            if (isset($seen[$value])) {
                throw new WorkspaceTurnException('Tell arena lineage contains a cycle.');
            }
            $seen[$value] = true;

            $record = $store->get($cursor);
            if (! $record instanceof CanonicalTurn) {
                throw new WorkspaceTurnException('Tell arena head must reference a canonical turn.');
            }
            if ($record->status() !== CanonicalTurnStatus::Completed) {
                throw new WorkspaceTurnException('Tell arena history contains a non-completed turn.');
            }

            $turnRoot = $record->lineage()->root();
            if ($root !== null && ! $root->equals($turnRoot)) {
                throw new WorkspaceTurnException('Tell arena lineage does not share one conversation root.');
            }
            $root = $turnRoot;
            $turns[] = new ArenaHistoryTurn($cursor, $record);
            $cursor = $record->lineage()->parent();
        }

        $rootRecord = $store->get($root);
        if (! $rootRecord instanceof CanonicalConversationRoot) {
            throw new WorkspaceTurnException('Tell arena lineage root is not a canonical conversation.');
        }

        $messages = $this->messagesFromCanonical($rootRecord->messages());
        foreach (array_reverse($turns) as $entry) {
            $messages = $this->appendTurn($messages, $entry->turn);
        }

        return new ArenaHistory($head, $head, $root, $messages, array_reverse($turns));
    }

    private function appendTurn(Messages $history, CanonicalTurn $turn): Messages
    {
        $messages = $this->messagesFromCanonical($turn->messages());
        if ($turn->toolCalls() === []) {
            return $history->appendMessages($messages);
        }

        $lastAssistant = null;
        foreach ($turn->messages() as $index => $message) {
            if ($message->role()->value === 'assistant') {
                $lastAssistant = $index;
            }
        }
        if ($lastAssistant === null) {
            throw new WorkspaceTurnException('Tell arena tool history requires an assistant response.');
        }

        [$toolCallMessage, $toolResultMessages] = $this->toolTrace($turn->toolCalls(), $turn->toolResults());
        $combined = $history;
        foreach ($messages->all() as $index => $message) {
            if ($index === $lastAssistant) {
                $combined = $combined
                    ->appendMessage($toolCallMessage)
                    ->appendMessages($toolResultMessages);
            }
            $combined = $combined->appendMessage($message);
        }

        return $combined;
    }

    /**
     * @param  list<CanonicalToolCall>  $calls
     * @param  list<CanonicalToolResult>  $results
     * @return array{0: Message, 1: Messages}
     */
    private function toolTrace(array $calls, array $results): array
    {
        $callsById = [];
        $toolCalls = [];
        foreach ($calls as $call) {
            $callsById[$call->id()] = $call;
            $toolCalls[] = new ToolCall($call->name(), $call->arguments(), $call->id());
        }

        $toolResults = Messages::empty();
        foreach ($results as $result) {
            $call = $callsById[$result->callId()] ?? null;
            if (! $call instanceof CanonicalToolCall) {
                throw new WorkspaceTurnException('Tell arena tool result has no matching tool call.');
            }
            $content = implode("\n", array_map(
                static fn ($part): string => $part->text(),
                $result->parts(),
            ));
            $toolResult = new ToolResult(
                content: $content,
                callId: $result->callId(),
                toolName: $call->name(),
                isError: $result->isError(),
            );
            $toolResults = $toolResults->appendMessage(
                Message::asTool($content)->withToolResult($toolResult),
            );
        }

        return [
            Message::asAssistant('')->withToolCalls(new ToolCalls(...$toolCalls)),
            $toolResults,
        ];
    }

    /** @param list<CanonicalMessage> $messages */
    private function messagesFromCanonical(array $messages): Messages
    {
        $compiled = Messages::empty();
        foreach ($messages as $message) {
            $parts = array_map(
                static fn ($part): ContentPart => ContentPart::text($part->text()),
                $message->parts(),
            );
            $compiled = $compiled->appendMessage(new Message(
                role: $message->role()->value,
                content: new Content(...$parts),
            ));
        }

        return $compiled;
    }
}
