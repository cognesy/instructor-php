<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Assembly;

use Cognesy\Messages\ContentPart;
use Cognesy\Messages\ContentParts;
use Cognesy\Messages\Enums\ContentType;
use Cognesy\Messages\Message;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Data\AssistantMessageChunk;
use Cognesy\Polyglot\Inference\Data\AssistantMessageChunks;
use Cognesy\Polyglot\Inference\Data\AssistantMessageChunkType;
use Cognesy\Polyglot\Inference\Data\ReplayEnvelope;
use RuntimeException;

/**
 * Canonical ordered block-to-assistant-message assembler for sync and streaming responses.
 */
final class AssistantMessageAssembler
{
    /**
     * @var array<string,array{
     *     type:string,
     *     text:string,
     *     toolId:string,
     *     toolName:string,
     *     toolArgs:string,
     *     block:?ContentPart,
     * }>
     */
    private array $blocks = [];
    /** @var list<string> */
    private array $order = [];
    private string $lastToolKey = '';
    private int $toolMutationCount = 0;
    private ?ReplayEnvelope $replay = null;
    private int $revision = 0;
    private int $assembledRevision = -1;
    private ?ContentParts $assembledParts = null;
    private ?ReplayEnvelope $assembledReplay = null;
    private ?Message $assembledMessage = null;
    private int $activeToolRevision = -1;
    private ?ToolCall $activeToolCall = null;

    public static function fromParts(ContentParts $parts, ?ReplayEnvelope $replay = null): self
    {
        $assembler = new self();
        foreach ($parts as $index => $part) {
            $assembler->apply(AssistantMessageChunk::blockEnd($index, $part));
        }
        if ($replay !== null) {
            $assembler->applyReplay($replay);
        }
        return $assembler;
    }

    public function applyAll(AssistantMessageChunks $chunks): void
    {
        foreach ($chunks as $chunk) {
            $this->apply($chunk);
        }
    }

    public function apply(AssistantMessageChunk $chunk): void
    {
        $changed = match ($chunk->type) {
            AssistantMessageChunkType::BlockStart => $this->start($chunk->index, $chunk->blockType),
            AssistantMessageChunkType::TextDelta,
            AssistantMessageChunkType::ReasoningDelta => $this->appendText($chunk),
            AssistantMessageChunkType::ToolCallDelta => $this->appendToolCall($chunk),
            AssistantMessageChunkType::BlockEnd => $this->close($chunk),
        };
        if ($changed) {
            $this->invalidate();
        }
    }

    public function applyReplay(ReplayEnvelope $replay): void
    {
        if ($this->replay === $replay) {
            return;
        }
        $this->replay = $replay;
        $this->invalidate();
    }

    public function message(): Message
    {
        $this->ensureAssembled();
        if ($this->assembledMessage instanceof Message) {
            return $this->assembledMessage;
        }

        $replay = $this->assembledReplay;
        $metadata = match ($replay) {
            null => [],
            default => [ReplayEnvelope::MESSAGE_METADATA_KEY => $replay->toArray()],
        };
        return $this->assembledMessage = new Message(
            role: 'assistant',
            parts: $this->assembledParts ?? ContentParts::empty(),
            metadata: $metadata,
        );
    }

    public function parts(): ContentParts
    {
        $this->ensureAssembled();
        return $this->assembledParts ?? ContentParts::empty();
    }

    public function replay(): ?ReplayEnvelope
    {
        $this->ensureAssembled();
        return $this->assembledReplay;
    }

    public function content(): string
    {
        return $this->message()->content()->toString();
    }

    public function reasoningContent(): string
    {
        return $this->message()->reasoningContent();
    }

    private function ensureAssembled(): void
    {
        if ($this->assembledRevision === $this->revision) {
            return;
        }

        $all = [];
        foreach ($this->order as $index) {
            $all[] = $this->assemble($index);
        }

        $replay = match (true) {
            $this->replay === null => null,
            $this->replay->alignsWith(count($all)) => $this->replay,
            default => null,
        };
        $kept = [];
        $parts = [];
        foreach ($all as $position => $part) {
            $keep = !$part->isEmpty() || ($replay?->hasPartData($position) ?? false);
            $kept[] = $keep;
            if ($keep) {
                $parts[] = $part;
            }
        }
        $this->assembledParts = new ContentParts(...$parts);
        $this->assembledReplay = $replay?->retaining($kept);
        $this->assembledMessage = null;
        $this->assembledRevision = $this->revision;
    }

    public function toolCalls(): ToolCalls
    {
        return $this->message()->toolCalls();
    }

    public function toolKey(): string
    {
        return $this->lastToolKey;
    }

    public function toolArgsSnapshot(): string
    {
        if ($this->lastToolKey === '' || !isset($this->blocks[$this->lastToolKey])) {
            return '';
        }
        return $this->blocks[$this->lastToolKey]['toolArgs'];
    }

    public function currentToolCall(): ?ToolCall
    {
        if ($this->lastToolKey === '') {
            return null;
        }
        if ($this->activeToolRevision === $this->revision) {
            return $this->activeToolCall;
        }

        $block = $this->blocks[$this->lastToolKey] ?? null;
        $this->activeToolCall = match (true) {
            $block === null => null,
            $block['block'] !== null => $block['block']->toToolCall(),
            default => new ToolCall(
                name: $block['toolName'],
                id: $block['toolId'],
                rawArguments: $block['toolArgs'],
            ),
        };
        $this->activeToolRevision = $this->revision;
        return $this->activeToolCall;
    }

    public function toolMutationCount(): int
    {
        return $this->toolMutationCount;
    }

    private function start(string $index, string $type): bool
    {
        if (isset($this->blocks[$index])) {
            return false;
        }
        $this->blocks[$index] = [
            'type' => $type,
            'text' => '',
            'toolId' => '',
            'toolName' => '',
            'toolArgs' => '',
            'block' => null,
        ];
        $this->order[] = $index;
        return true;
    }

    private function appendText(AssistantMessageChunk $chunk): bool
    {
        $started = $this->start($chunk->index, $chunk->blockType);
        if ($this->blocks[$chunk->index]['block'] !== null) {
            return $started;
        }
        if ($chunk->text === '') {
            return $started;
        }
        $this->blocks[$chunk->index]['text'] .= $chunk->text;
        return true;
    }

    private function appendToolCall(AssistantMessageChunk $chunk): bool
    {
        $isNew = !isset($this->blocks[$chunk->index]);
        $this->start($chunk->index, ContentType::ToolCall->value);
        if ($this->blocks[$chunk->index]['block'] !== null) {
            return $isNew;
        }

        $changed = $isNew || $this->lastToolKey !== $chunk->index;
        $this->lastToolKey = $chunk->index;
        if ($changed) {
            $this->toolMutationCount += 1;
        }

        $toolId = match (true) {
            is_string($chunk->toolCallId) => $chunk->toolCallId,
            $chunk->toolCallId !== null => $chunk->toolCallId->toString(),
            default => '',
        };
        if ($toolId !== '' && $toolId !== $this->blocks[$chunk->index]['toolId']) {
            $this->blocks[$chunk->index]['toolId'] = $toolId;
            $changed = true;
        }
        if ($chunk->toolCallName !== '' && $chunk->toolCallName !== $this->blocks[$chunk->index]['toolName']) {
            $this->blocks[$chunk->index]['toolName'] = $chunk->toolCallName;
            $this->toolMutationCount += 1;
            $changed = true;
        }
        if ($chunk->toolCallArguments !== '') {
            $this->blocks[$chunk->index]['toolArgs'] .= $chunk->toolCallArguments;
            $this->toolMutationCount += 1;
            $changed = true;
        }
        return $changed;
    }

    private function close(AssistantMessageChunk $chunk): bool
    {
        $started = $this->start($chunk->index, $chunk->blockType);
        if ($this->blocks[$chunk->index]['block'] !== null || $chunk->block === null) {
            return $started;
        }
        $this->blocks[$chunk->index]['block'] = $chunk->block;
        if ($chunk->block->type() === ContentType::ToolCall->value) {
            $this->lastToolKey = $chunk->index;
            $this->toolMutationCount += 1;
        }
        return true;
    }

    private function assemble(string $index): ContentPart
    {
        $block = $this->blocks[$index] ?? null;
        if ($block === null) {
            throw new RuntimeException("Assistant message assembler invariant violated for block '{$index}'.");
        }
        if ($block['block'] !== null) {
            return $block['block'];
        }

        return match ($block['type']) {
            ContentType::Text->value => ContentPart::text($block['text']),
            ContentType::Reasoning->value => ContentPart::reasoning($block['text']),
            ContentType::ToolCall->value => ContentPart::toolCall(ToolCall::fromArray([
                'id' => $block['toolId'],
                'name' => $block['toolName'],
                'arguments' => $block['toolArgs'],
            ])),
            default => throw new RuntimeException("Cannot assemble incomplete assistant block of type '{$block['type']}'."),
        };
    }

    private function invalidate(): void
    {
        $this->revision += 1;
        $this->assembledMessage = null;
        $this->activeToolCall = null;
    }

}
