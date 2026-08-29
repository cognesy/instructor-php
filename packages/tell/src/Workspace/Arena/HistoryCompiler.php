<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Messages\ToolResult;
use Cognesy\Tell\Workspace\Arena\Record\ConversationRoot;
use Cognesy\Tell\Workspace\Arena\Record\Message as RecordMessage;
use Cognesy\Tell\Workspace\Arena\Record\ToolCall as RecordToolCall;
use Cognesy\Tell\Workspace\Arena\Record\ToolResult as RecordToolResult;
use Cognesy\Tell\Workspace\Arena\Record\Turn;
use Cognesy\Tell\Workspace\Arena\Record\TurnStatus;
use Cognesy\Tell\Workspace\Execution\TurnException;

/**
 * Compiles Arena record ancestry into the message/tool transcript accepted by Agents.
 *
 * Provider configuration remains outside this compiler: its output is determined
 * exclusively by immutable arena records.
 */
final class HistoryCompiler
{
    public function compile(CanUseArena $store, ?ObjectHash $head): History {
        if ($head === null) {
            return new History(null, null, null, Messages::empty(), []);
        }

        $headRecord = $store->get($head);
        if ($headRecord instanceof ConversationRoot) {
            return new History(
                referenceHead: $head,
                turnHead: null,
                root: $head,
                messages: $this->messagesFromRecords($headRecord->messages()),
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
                throw new TurnException('Tell arena lineage contains a cycle.');
            }
            $seen[$value] = true;

            $record = $store->get($cursor);
            if (!$record instanceof Turn) {
                throw new TurnException('Tell arena head must reference a turn record.');
            }
            if ($record->status() !== TurnStatus::Completed) {
                throw new TurnException('Tell arena history contains a non-completed turn.');
            }

            $turnRoot = $record->lineage()->root();
            if ($root !== null && !$root->equals($turnRoot)) {
                throw new TurnException('Tell arena lineage does not share one conversation root.');
            }
            $root = $turnRoot;
            $turns[] = new HistoryTurn($cursor, $record);
            $cursor = $record->lineage()->parent();
        }

        $rootRecord = $store->get($root);
        if (!$rootRecord instanceof ConversationRoot) {
            throw new TurnException('Tell arena lineage root is not a conversation record.');
        }

        $messages = $this->messagesFromRecords($rootRecord->messages());
        foreach (array_reverse($turns) as $entry) {
            $messages = $this->appendTurn($messages, $entry->turn);
        }

        return new History($head, $head, $root, $messages, array_reverse($turns));
    }

    private function appendTurn(Messages $history, Turn $turn): Messages {
        $messages = $this->messagesFromRecords($turn->messages());
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
            throw new TurnException('Tell arena tool history requires an assistant response.');
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
     * @param  list<RecordToolCall>  $calls
     * @param  list<RecordToolResult>  $results
     * @return array{0: Message, 1: Messages}
     */
    private function toolTrace(array $calls, array $results): array {
        $callsById = [];
        $toolCalls = [];
        foreach ($calls as $call) {
            $callsById[$call->id()] = $call;
            $toolCalls[] = new ToolCall($call->name(), $call->arguments(), $call->id());
        }

        $toolResults = Messages::empty();
        foreach ($results as $result) {
            $call = $callsById[$result->callId()] ?? null;
            if (!$call instanceof RecordToolCall) {
                throw new TurnException('Tell arena tool result has no matching tool call.');
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

    /** @param list<RecordMessage> $messages */
    private function messagesFromRecords(array $messages): Messages {
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
