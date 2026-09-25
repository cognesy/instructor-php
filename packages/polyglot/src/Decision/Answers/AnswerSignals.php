<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Answers;

use Cognesy\Polyglot\Decision\Internal\DecisionData;

final readonly class AnswerSignals
{
    private ?float $modelActionProbability;

    public function __construct(?float $modelActionProbability = null)
    {
        $this->modelActionProbability = match ($modelActionProbability) {
            null => null,
            default => DecisionData::probability($modelActionProbability, 'Model action probability'),
        };
    }

    public static function empty(): self
    {
        return new self;
    }

    public static function fromArray(array $data): self
    {
        DecisionData::assertKnownFields($data, ['modelActionProbability'], 'answer signals');

        return new self(
            modelActionProbability: match (array_key_exists('modelActionProbability', $data)) {
                true => DecisionData::probability(
                    $data['modelActionProbability'],
                    'Model action probability',
                ),
                false => null,
            },
        );
    }

    public function modelActionProbability(): ?float
    {
        return $this->modelActionProbability;
    }

    public function isEmpty(): bool
    {
        return $this->modelActionProbability === null;
    }

    public function toArray(): array
    {
        return match ($this->modelActionProbability) {
            null => [],
            default => ['modelActionProbability' => $this->modelActionProbability],
        };
    }
}
