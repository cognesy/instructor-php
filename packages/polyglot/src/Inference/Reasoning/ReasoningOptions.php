<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

/** Provider options produced at the transport edge. */
final readonly class ReasoningOptions
{
    public function __construct(private array $values = []) {}

    public static function empty(): self
    {
        return new self;
    }

    public function toArray(): array
    {
        return $this->values;
    }
}
