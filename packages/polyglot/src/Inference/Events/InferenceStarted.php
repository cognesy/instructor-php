<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use DateTimeImmutable;

/**
 * Dispatched when an inference request begins execution.
 * Use for timing/latency measurement in conjunction with InferenceCompleted.
 */
final class InferenceStarted extends InferenceEvent
{
    public readonly DateTimeImmutable $startedAt;

    public function __construct(
        public readonly string $executionId,
        public readonly InferenceRequest $request,
        public readonly bool $isStreamed,
    ) {
        parent::__construct([
            'executionId' => $this->executionId,
            'isStreamed' => $this->isStreamed,
            'model' => $this->request->model(),
            'messageCount' => count($this->request->messages()),
        ]);
        $this->startedAt = new DateTimeImmutable();
    }

    #[\Override]
    public function __toString(): string {
        return sprintf(
            'Inference started [%s] model=%s streamed=%s',
            $this->executionId,
            $this->request->model(),
            $this->isStreamed ? 'true' : 'false'
        );
    }
}
