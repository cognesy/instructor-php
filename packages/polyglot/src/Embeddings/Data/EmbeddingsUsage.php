<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Data;

use Cognesy\Utils\Profiler\TracksObjectCreation;

class EmbeddingsUsage
{
    use TracksObjectCreation;

    public function __construct(
        public int $inputTokens = 0,
    ) {
        $this->trackObjectCreation();
    }

    // CONSTRUCTORS ///////////////////////////////////////////////////////

    public static function none(): self
    {
        return new self();
    }

    public static function fromArray(array $value): self
    {
        return new self(
            inputTokens: (int) ($value['input'] ?? 0),
        );
    }

    // ACCESSORS /////////////////////////////////////////////////////////

    public function total(): int
    {
        return $this->inputTokens;
    }

    public function input(): int
    {
        return $this->inputTokens;
    }

    // MUTATORS ///////////////////////////////////////////////////////////

    public function withAccumulated(EmbeddingsUsage $usage): self
    {
        return new self(
            inputTokens: $this->inputTokens + $usage->inputTokens,
        );
    }

    // SERIALIZATION ///////////////////////////////////////////////////////

    public function toString(): string
    {
        return "Tokens: {$this->inputTokens} (i:{$this->inputTokens})";
    }

    public function toArray(): array
    {
        return [
            'input' => $this->inputTokens,
        ];
    }
}
