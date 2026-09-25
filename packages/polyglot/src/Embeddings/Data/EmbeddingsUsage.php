<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Data;

use Cognesy\Utils\Profiler\TracksObjectCreation;
use InvalidArgumentException;

class EmbeddingsUsage
{
    use TracksObjectCreation;

    public function __construct(
        public ?int $inputTokens = null,
    ) {
        if ($inputTokens !== null && $inputTokens < 0) {
            throw new InvalidArgumentException('Embedding input token count must be non-negative or null.');
        }
        $this->trackObjectCreation();
    }

    // CONSTRUCTORS ///////////////////////////////////////////////////////

    public static function none(): self
    {
        return new self;
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromArray(array $value): self
    {
        return new self(
            inputTokens: self::optionalNonNegativeInt($value['input'] ?? null),
        );
    }

    // ACCESSORS /////////////////////////////////////////////////////////

    public function total(): ?int
    {
        return $this->inputTokens;
    }

    public function input(): ?int
    {
        return $this->inputTokens;
    }

    // MUTATORS ///////////////////////////////////////////////////////////

    public function withAccumulated(EmbeddingsUsage $usage): self
    {
        if ($this->inputTokens === null || $usage->inputTokens === null) {
            return self::none();
        }

        return new self(
            inputTokens: $this->inputTokens + $usage->inputTokens,
        );
    }

    // SERIALIZATION ///////////////////////////////////////////////////////

    public function toString(): string
    {
        return match ($this->inputTokens) {
            null => 'Tokens: unknown',
            default => "Tokens: {$this->inputTokens} (i:{$this->inputTokens})",
        };
    }

    public function toArray(): array
    {
        return [
            'input' => $this->inputTokens,
        ];
    }

    private static function optionalNonNegativeInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (! is_int($value) || $value < 0) {
            throw new InvalidArgumentException('Embedding input token count must be a non-negative integer or null.');
        }

        return $value;
    }
}
