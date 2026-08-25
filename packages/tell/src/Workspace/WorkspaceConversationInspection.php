<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Canonical\CanonicalHash;
use Cognesy\Tell\Canonical\CanonicalMessage;
use Cognesy\Tell\Canonical\CanonicalTurn;
use Cognesy\Tell\Render\ContentPreview;
use JsonException;

/**
 * Bounded, provider-independent views of one verified canonical conversation.
 */
final readonly class WorkspaceConversationInspection
{
    public function __construct(
        private ?SessionId $sessionId,
        private ?CanonicalHash $head,
        private ArenaHistory $history,
    ) {}

    /** @return array{name: string, type: 'main'|'session'} */
    public function selector(): array
    {
        return match ($this->sessionId) {
            null => ['type' => 'main', 'name' => 'main'],
            default => ['type' => 'session', 'name' => $this->sessionId->toString()],
        };
    }

    public function head(): ?CanonicalHash
    {
        return $this->head;
    }

    public function history(): ArenaHistory
    {
        return $this->history;
    }

    /** @return list<array<string, mixed>> */
    public function historyRows(bool $full): array
    {
        return array_map(
            fn (ArenaHistoryTurn $entry): array => $this->historyRow($entry, $full),
            $this->history->turns,
        );
    }

    /** @return list<array<string, mixed>> */
    public function transcriptRows(bool $full): array
    {
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

    private function historyRow(ArenaHistoryTurn $entry, bool $full): array
    {
        $turn = $entry->turn;
        $preview = ContentPreview::from($this->turnContent($turn), $full);

        return [
            'head' => $entry->hash->toString(),
            'id' => $turn->id(),
            'parent' => $turn->lineage()->parent()?->toString(),
            'root' => $turn->lineage()->root()->toString(),
            'status' => $turn->status()->value,
            'compactedFrom' => array_map(
                static fn (CanonicalHash $hash): string => $hash->toString(),
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

    private function turnContent(CanonicalTurn $turn): string
    {
        return implode("\n", array_map(
            fn (CanonicalMessage $message): string => $this->messageContent($message),
            $turn->messages(),
        ));
    }

    private function messageContent(CanonicalMessage $message): string
    {
        return implode("\n", array_map(
            static fn ($part): string => $part->text(),
            $message->parts(),
        ));
    }

    /** @param array<string, mixed> $arguments */
    private function toolCallRow(string $id, string $name, array $arguments, bool $full): array
    {
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
