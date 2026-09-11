<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

use Cognesy\Messages\ToolCallId;

/** Resolves stable identity and duplicate-suppressed semantics for one OpenResponses stream. */
final class OpenResponsesStreamContext
{
    private ?OpenResponseItemId $currentItemId = null;
    /** @var array<string,ToolCallId> */
    private array $itemToCallId = [];
    /** @var array<string,string> */
    private array $itemToName = [];
    /** @var array<string,bool> */
    private array $seenTextBlocks = [];
    /** @var array<string,bool> */
    private array $seenReasoningBlocks = [];
    /** @var array<string,bool> */
    private array $seenToolArgumentBlocks = [];

    /** @param array<string,mixed> $data */
    public function resolve(array $data): OpenResponsesStreamEvent
    {
        $type = (string) ($data['type'] ?? '');
        $item = is_array($data['item'] ?? null) ? $data['item'] : null;
        $response = is_array($data['response'] ?? null) ? $data['response'] : null;
        $itemId = $this->resolveItemId($data, $item);
        $itemIdentity = $itemId?->toString() ?? 'output:' . (string) ($data['output_index'] ?? 0);
        $contentIndex = (string) ($data['content_index'] ?? 0);

        if ($itemId !== null) {
            $this->currentItemId = $itemId;
        }
        $this->rememberToolItem($item, $itemIdentity, $itemId);

        $toolCallId = $this->resolveToolCallId($type, $data, $item, $itemIdentity, $itemId);
        $toolName = $this->resolveToolName($type, $data, $item, $itemIdentity);
        $textBlockKey = OpenResponsesBlockKey::text($itemIdentity, $contentIndex);
        $reasoningBlockKey = OpenResponsesBlockKey::reasoning($itemIdentity);
        $toolBlockKey = OpenResponsesBlockKey::tool($toolCallId, $itemIdentity);

        return new OpenResponsesStreamEvent(
            wireData: $data,
            type: $type,
            item: $item,
            response: $response,
            itemId: $itemId,
            toolCallId: $toolCallId,
            toolName: $toolName,
            contentIndex: $contentIndex,
            textDelta: $this->resolveTextDelta($type, $data, $textBlockKey),
            reasoningDelta: $this->resolveReasoningDelta($type, $data, $item, $reasoningBlockKey),
            toolArgumentsDelta: $this->resolveToolArgumentsDelta($type, $data, $item, $toolBlockKey),
            textBlockKey: $textBlockKey,
            reasoningBlockKey: $reasoningBlockKey,
            toolBlockKey: $toolBlockKey,
        );
    }

    /**
     * @param array<string,mixed> $data
     * @param null|array<string,mixed> $item
     */
    private function resolveItemId(array $data, ?array $item): ?OpenResponseItemId
    {
        $id = $data['item_id'] ?? $item['id'] ?? null;
        if (is_string($id) && $id !== '') {
            return OpenResponseItemId::fromString($id);
        }
        if (array_key_exists('output_index', $data)) {
            return null;
        }
        return $this->currentItemId;
    }

    /** @param null|array<string,mixed> $item */
    private function rememberToolItem(?array $item, string $itemIdentity, ?OpenResponseItemId $itemId): void
    {
        if (($item['type'] ?? '') !== 'function_call') {
            return;
        }
        $callId = $item['call_id'] ?? null;
        $resolvedCallId = match (true) {
            is_string($callId) && $callId !== '' => ToolCallId::fromString($callId),
            $itemId !== null => ToolCallId::fromString($itemId->toString()),
            default => null,
        };
        if ($resolvedCallId !== null) {
            $this->itemToCallId[$itemIdentity] = $resolvedCallId;
        }
        $name = $item['name'] ?? null;
        if (is_string($name) && $name !== '') {
            $this->itemToName[$itemIdentity] = $name;
        }
    }

    /**
     * @param array<string,mixed> $data
     * @param null|array<string,mixed> $item
     */
    private function resolveToolCallId(
        string $type,
        array $data,
        ?array $item,
        string $itemIdentity,
        ?OpenResponseItemId $itemId,
    ): ?ToolCallId {
        if (!$this->isToolEvent($type, $item)) {
            return null;
        }
        $id = $data['call_id'] ?? $item['call_id'] ?? null;
        if (is_string($id) && $id !== '') {
            return ToolCallId::fromString($id);
        }
        if (isset($this->itemToCallId[$itemIdentity])) {
            return $this->itemToCallId[$itemIdentity];
        }
        return match ($itemId) {
            null => null,
            default => ToolCallId::fromString($itemId->toString()),
        };
    }

    /**
     * @param array<string,mixed> $data
     * @param null|array<string,mixed> $item
     */
    private function resolveToolName(string $type, array $data, ?array $item, string $itemIdentity): string
    {
        if (!$this->isToolEvent($type, $item)) {
            return '';
        }
        $name = $data['name'] ?? $item['name'] ?? null;
        return match (true) {
            is_string($name) && $name !== '' => $name,
            default => $this->itemToName[$itemIdentity] ?? '',
        };
    }

    /** @param null|array<string,mixed> $item */
    private function isToolEvent(string $type, ?array $item): bool
    {
        return str_starts_with($type, 'response.function_call_arguments')
            || ($item['type'] ?? '') === 'function_call';
    }

    /** @param array<string,mixed> $data */
    private function resolveTextDelta(string $type, array $data, string $blockKey): string
    {
        return match ($type) {
            'response.output_text.delta',
            'response.text.delta' => $this->rememberDelta($this->seenTextBlocks, $blockKey, $data['delta'] ?? ''),
            'response.output_text.done',
            'response.text.done' => $this->emitUnlessSeen($this->seenTextBlocks, $blockKey, $data['text'] ?? ''),
            default => '',
        };
    }

    /**
     * @param array<string,mixed> $data
     * @param null|array<string,mixed> $item
     */
    private function resolveReasoningDelta(string $type, array $data, ?array $item, string $blockKey): string
    {
        return match ($type) {
            'response.reasoning_text.delta',
            'response.reasoning.delta',
            'response.reasoning_summary_text.delta' => $this->rememberDelta(
                $this->seenReasoningBlocks,
                $blockKey,
                $data['delta'] ?? '',
            ),
            'response.reasoning_text.done',
            'response.reasoning_summary_text.done' => $this->emitUnlessSeen(
                $this->seenReasoningBlocks,
                $blockKey,
                $data['text'] ?? '',
            ),
            'response.output_item.done' => match ($item['type'] ?? '') {
                'reasoning' => $this->emitUnlessSeen(
                    $this->seenReasoningBlocks,
                    $blockKey,
                    OpenResponsesReplay::reasoningText($item ?? []),
                ),
                default => '',
            },
            default => '',
        };
    }

    /**
     * @param array<string,mixed> $data
     * @param null|array<string,mixed> $item
     */
    private function resolveToolArgumentsDelta(string $type, array $data, ?array $item, string $blockKey): string
    {
        return match ($type) {
            'response.function_call_arguments.delta' => $this->rememberDelta(
                $this->seenToolArgumentBlocks,
                $blockKey,
                $data['delta'] ?? '',
            ),
            'response.function_call_arguments.done' => $this->emitUnlessSeen(
                $this->seenToolArgumentBlocks,
                $blockKey,
                $data['arguments'] ?? '',
            ),
            'response.output_item.done' => match ($item['type'] ?? '') {
                'function_call' => $this->emitUnlessSeen(
                    $this->seenToolArgumentBlocks,
                    $blockKey,
                    $item['arguments'] ?? '',
                ),
                default => '',
            },
            default => '',
        };
    }

    /** @param array<string,bool> $seen */
    private function rememberDelta(array &$seen, string $blockKey, mixed $delta): string
    {
        $seen[$blockKey] = true;
        return is_string($delta) ? $delta : '';
    }

    /** @param array<string,bool> $seen */
    private function emitUnlessSeen(array $seen, string $blockKey, mixed $complete): string
    {
        if ($seen[$blockKey] ?? false) {
            return '';
        }
        return is_string($complete) ? $complete : '';
    }
}
