<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Anthropic;

use Cognesy\Polyglot\Inference\Assembly\ReplayAccumulator;
use Cognesy\Polyglot\Inference\Data\ReplayEnvelope;

/**
 * Per-stream Anthropic replay state keyed by wire content-block index.
 */
final class AnthropicReplayState
{
    private ReplayAccumulator $replay;

    public function __construct()
    {
        $this->replay = new ReplayAccumulator(AnthropicReplay::OWNER);
    }

    /** @param array<string,mixed> $data */
    public function observe(array $data, ?string $blockIndex): void
    {
        $this->observeResponse($data);
        if ($blockIndex === null || !$this->isContentBlockEvent($data)) {
            return;
        }
        $this->replay->remember($blockIndex);

        $contentBlock = $data['content_block'] ?? null;
        if (is_array($contentBlock)) {
            $metadata = AnthropicReplay::metadataForWirePart($contentBlock);
            $this->replay->remember($blockIndex, $metadata);
        }

        $signature = $data['delta']['signature'] ?? null;
        if (!is_string($signature) || $signature === '') {
            return;
        }
        $current = $this->replay->part($blockIndex) ?? ['kind' => 'thinking'];
        if (!is_array($current) || ($current['kind'] ?? '') !== 'thinking') {
            return;
        }
        $current['signature'] = ($current['signature'] ?? '') . $signature;
        $this->replay->remember($blockIndex, $current);
    }

    public function replay(): ?ReplayEnvelope
    {
        return $this->replay->envelope();
    }

    /** @param array<string,mixed> $data */
    private function observeResponse(array $data): void
    {
        $message = $data['message'] ?? null;
        if (!is_array($message)) {
            return;
        }
        $this->replay->rememberResponse(array_filter([
            'id' => $message['id'] ?? null,
            'model' => $message['model'] ?? null,
        ], static fn(mixed $value): bool => is_string($value) && $value !== ''));
    }

    /** @param array<string,mixed> $data */
    private function isContentBlockEvent(array $data): bool
    {
        return isset($data['content_block'])
            || isset($data['delta']['text'])
            || isset($data['delta']['thinking_delta'])
            || isset($data['delta']['partial_json'])
            || isset($data['delta']['signature']);
    }
}
