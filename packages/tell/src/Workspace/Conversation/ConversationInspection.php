<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Conversation;

use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Render\ContentPreview;
use Cognesy\Tell\Workspace\Arena\History;
use Cognesy\Tell\Workspace\Arena\HistoryTurn;
use Cognesy\Tell\Workspace\Arena\ObjectHash;
use Cognesy\Tell\Workspace\Arena\Record\Message as RecordMessage;
use Cognesy\Tell\Workspace\Arena\Record\Turn;
use Cognesy\Tell\Workspace\Branch\ResolvedBranch;
use Cognesy\Tell\Workspace\WorkspaceException;
use JsonException;

/**
 * Bounded, provider-independent views of one verified canonical conversation.
 */
final readonly class ConversationInspection
{
    public function __construct(
        private ?SessionId $sessionId,
        private ?ObjectHash $head,
        private History $history,
        private ?ResolvedBranch $branch = null,
        private ?ObjectHash $immutableRef = null,
    ) {}

    /** @return array{name: string, type: 'main'|'branch'|'session'|'ref', source?: string} */
    public function selector(): array {
        if ($this->immutableRef !== null) {
            return ['type' => 'ref', 'name' => $this->immutableRef->toString()];
        }

        return match ($this->sessionId) {
            null => match ($this->branch) {
                null => ['type' => 'main', 'name' => 'main'],
                default => $this->branch->branch === 'main'
                    ? array_filter([
                        'type' => 'main',
                        'name' => 'main',
                        'source' => $this->branch->invocationLocal ? 'invocation' : null,
                    ], static fn (mixed $value): bool => $value !== null)
                    : ['type' => 'branch', 'name' => $this->branch->branch, 'source' => $this->branch->invocationLocal ? 'invocation' : 'current'],
            },
            default => ['type' => 'session', 'name' => $this->sessionId->toString()],
        };
    }

    public function head(): ?ObjectHash {
        return $this->head;
    }

    public function history(): History {
        return $this->history;
    }

    /** @return list<array<string, mixed>> */
    public function historyRows(bool $full): array {
        return array_map(
            fn (HistoryTurn $entry): array => $this->historyRow($entry, $full),
            $this->history->turns,
        );
    }

    /** @return list<array<string, mixed>> */
    public function transcriptRows(bool $full): array {
        $rows = [];
        foreach ($this->history->messages->all() as $index => $message) {
            $preview = ContentPreview::from($message->content()->toString(), $full);
            $row = [
                'index' => $index + 1,
                'role' => $message->role()->value,
                'content' => $preview->content,
                'characters' => $preview->characters,
                'truncated' => $preview->truncated,
            ];
            if ($message->hasToolCalls()) {
                $row['toolCalls'] = array_map(
                    fn ($call): array => $this->toolCallRow($call->idString(), $call->name(), $call->arguments(), $full),
                    $message->toolCalls()->all(),
                );
            }
            if ($message->hasToolResult()) {
                $result = $message->toolResult();
                if ($result === null) {
                    throw new WorkspaceException('Tell transcript contains an invalid tool result.');
                }
                $resultPreview = ContentPreview::from($result->content(), $full);
                $row['toolResult'] = [
                    'callId' => $result->callIdString(),
                    'name' => $result->toolName(),
                    'isError' => $result->isError(),
                    'content' => $resultPreview->content,
                    'characters' => $resultPreview->characters,
                    'truncated' => $resultPreview->truncated,
                ];
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function historyRow(HistoryTurn $entry, bool $full): array {
        $turn = $entry->turn;
        $preview = ContentPreview::from($this->turnContent($turn), $full);

        return [
            'head' => $entry->hash->toString(),
            'id' => $turn->id(),
            'parent' => $turn->lineage()->parent()?->toString(),
            'root' => $turn->lineage()->root()->toString(),
            'status' => $turn->status()->value,
            'compactedFrom' => array_map(
                static fn (ObjectHash $hash): string => $hash->toString(),
                $turn->lineage()->compactedFrom(),
            ),
            'messageCount' => count($turn->messages()),
            'toolCallCount' => count($turn->toolCalls()),
            'toolResultCount' => count($turn->toolResults()),
            'content' => $preview->content,
            'characters' => $preview->characters,
            'truncated' => $preview->truncated,
        ];
    }

    private function turnContent(Turn $turn): string {
        return implode("\n", array_map(
            fn (RecordMessage $message): string => $this->messageContent($message),
            $turn->messages(),
        ));
    }

    private function messageContent(RecordMessage $message): string {
        return implode("\n", array_map(
            static fn ($part): string => $part->text(),
            $message->parts(),
        ));
    }

    /** @param array<string, mixed> $arguments */
    private function toolCallRow(string $id, string $name, array $arguments, bool $full): array {
        try {
            $argumentsJson = json_encode(
                $arguments,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $exception) {
            throw new WorkspaceException('Tell canonical tool arguments cannot be rendered safely.', previous: $exception);
        }
        $preview = ContentPreview::from($argumentsJson, $full);

        return [
            'id' => $id,
            'name' => $name,
            'arguments' => $preview->content,
            'argumentCharacters' => $preview->characters,
            'argumentsTruncated' => $preview->truncated,
        ];
    }
}
