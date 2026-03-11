<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Utils\Json\Json;

final class PartialInferenceDeltaCreated extends InferenceEvent
{
    public function __construct(
        public string $executionId,
        public PartialInferenceDelta $partialInferenceDelta,
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode($this->toArray());
    }

    #[\Override]
    public function toArray() : array {
        return [
            'executionId' => $this->executionId,
            'partialInferenceDelta' => [
                'contentDelta' => $this->partialInferenceDelta->contentDelta,
                'reasoningContentDelta' => $this->partialInferenceDelta->reasoningContentDelta,
                'toolId' => is_string($this->partialInferenceDelta->toolId)
                    ? $this->partialInferenceDelta->toolId
                    : ($this->partialInferenceDelta->toolId?->toString() ?? ''),
                'toolName' => $this->partialInferenceDelta->toolName,
                'toolArgs' => $this->partialInferenceDelta->toolArgs,
                'finishReason' => $this->partialInferenceDelta->finishReason,
                'usage' => $this->partialInferenceDelta->usage?->toArray() ?? [],
                'hasValue' => $this->partialInferenceDelta->value !== null,
                'value' => $this->normalizeValue($this->partialInferenceDelta->value),
            ],
        ];
    }

    private function normalizeValue(mixed $value): mixed
    {
        return match (true) {
            is_null($value),
            is_scalar($value),
            is_array($value) => $value,
            $value instanceof \JsonSerializable => $value->jsonSerialize(),
            $value instanceof \Stringable => (string) $value,
            is_object($value) => ['type' => $value::class],
            default => get_debug_type($value),
        };
    }
}
