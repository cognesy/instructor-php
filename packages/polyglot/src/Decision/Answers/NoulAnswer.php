<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Answers;

use Cognesy\Polyglot\Decision\Internal\DecisionData;

final readonly class NoulAnswer
{
    private string $questionId;

    private float $probability;

    public function __construct(string $questionId, float $probability)
    {
        $this->questionId = DecisionData::nonEmptyString($questionId, 'Noul answer question ID');
        $this->probability = DecisionData::probability($probability, 'Noul answer probability');
    }

    public static function fromArray(array $data): self
    {
        DecisionData::assertKnownFields($data, ['id', 'type', 'probability'], 'Noul answer');
        if (($data['type'] ?? null) !== 'noul') {
            throw new \InvalidArgumentException('Noul answer type must be noul.');
        }

        return new self(
            questionId: DecisionData::nonEmptyString($data['id'] ?? null, 'Noul answer question ID'),
            probability: DecisionData::probability($data['probability'] ?? null, 'Noul answer probability'),
        );
    }

    public function questionId(): string
    {
        return $this->questionId;
    }

    public function probability(): float
    {
        return $this->probability;
    }

    public function toArray(): array
    {
        return ['id' => $this->questionId, 'type' => 'noul', 'probability' => $this->probability];
    }
}
