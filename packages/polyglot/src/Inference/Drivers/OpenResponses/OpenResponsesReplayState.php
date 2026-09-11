<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

use Cognesy\Polyglot\Inference\Assembly\ReplayAccumulator;
use Cognesy\Polyglot\Inference\Data\ReplayEnvelope;

/**
 * Per-stream OpenAI Responses block ordering and private reasoning state.
 */
final class OpenResponsesReplayState
{
    private ReplayAccumulator $replay;

    public function __construct()
    {
        $this->replay = new ReplayAccumulator(OpenResponsesReplay::OWNER);
    }

    public function observe(OpenResponsesStreamEvent $event): void
    {
        $item = $event->item();
        if ($item !== null) {
            $this->observeItem($event, $item);
        }

        if ($event->isReasoningEvent()) {
            $this->replay->remember($event->reasoningBlockKey());
        }
        if ($event->isTextEvent()) {
            $this->replay->remember($event->textBlockKey());
        }

        $response = $event->response();
        if ($response !== null) {
            $this->replay->rememberResponse(array_filter([
                'id' => $response['id'] ?? null,
                'model' => $response['model'] ?? null,
                'status' => $response['status'] ?? null,
            ], static fn(mixed $value): bool => is_string($value) && $value !== ''));
        }
    }

    public function replay(): ?ReplayEnvelope
    {
        return $this->replay->envelope();
    }

    /** @param array<string,mixed> $item */
    private function observeItem(OpenResponsesStreamEvent $event, array $item): void
    {
        $type = (string) ($item['type'] ?? '');
        $key = match ($type) {
            'reasoning' => $event->reasoningBlockKey(),
            'function_call' => $event->toolBlockKey(),
            default => '',
        };
        if ($key === '') {
            return;
        }
        $this->replay->remember($key);
        $metadata = OpenResponsesReplay::metadataForReasoningItem($item);
        $this->replay->remember($key, $metadata);
    }
}
