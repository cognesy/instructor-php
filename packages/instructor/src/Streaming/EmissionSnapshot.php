<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming;

readonly final class EmissionSnapshot
{
    public function __construct(
        public string $content = '',
        public string $finishReason = '',
        public string $toolKey = '',
        public string $toolArgsSnapshot = '',
        public mixed $value = null,
    ) {}

    public static function fromState(StructuredOutputStreamState $state): self
    {
        return new self(
            content: $state->content(),
            finishReason: $state->finishReason(),
            toolKey: $state->toolKey(),
            toolArgsSnapshot: $state->toolArgsSnapshot(),
            value: $state->value(),
        );
    }

    public function hasValue(): bool
    {
        return $this->value !== null;
    }
}
