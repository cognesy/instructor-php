<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Answers;

use Cognesy\Polyglot\Decision\Collections\ChoiceProbabilities;
use Cognesy\Polyglot\Decision\Internal\DecisionData;
use InvalidArgumentException;

final readonly class ChoiceAnswer
{
    private const float MAXIMUM_TOLERANCE = 0.000000001;

    private string $questionId;

    private string $value;

    private float $confidence;

    public function __construct(
        string $questionId,
        string $value,
        float $confidence,
        private ChoiceProbabilities $probabilities,
    ) {
        $this->questionId = DecisionData::nonEmptyString($questionId, 'Choice answer question ID');
        $this->value = DecisionData::nonEmptyString($value, 'Choice answer value');
        $this->confidence = DecisionData::probability($confidence, 'Choice answer confidence');
        if (! $probabilities->has($value)) {
            throw new InvalidArgumentException("Choice answer value '{$value}' has no probability.");
        }
        if ($probabilities->probability($value) < $probabilities->highest() - self::MAXIMUM_TOLERANCE) {
            throw new InvalidArgumentException('Choice answer value must have the highest probability.');
        }
    }

    public static function fromArray(array $data): self
    {
        DecisionData::assertKnownFields($data, ['id', 'type', 'value', 'confidence', 'probabilities'], 'Choice answer');
        if (($data['type'] ?? null) !== 'choice') {
            throw new InvalidArgumentException('Choice answer type must be choice.');
        }
        if (! is_array($data['probabilities'] ?? null)) {
            throw new InvalidArgumentException('Choice answer probabilities must be a list.');
        }

        return new self(
            questionId: DecisionData::nonEmptyString($data['id'] ?? null, 'Choice answer question ID'),
            value: DecisionData::nonEmptyString($data['value'] ?? null, 'Choice answer value'),
            confidence: DecisionData::probability($data['confidence'] ?? null, 'Choice answer confidence'),
            probabilities: ChoiceProbabilities::fromArray($data['probabilities']),
        );
    }

    public function questionId(): string
    {
        return $this->questionId;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function confidence(): float
    {
        return $this->confidence;
    }

    public function probabilities(): ChoiceProbabilities
    {
        return $this->probabilities;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->questionId,
            'type' => 'choice',
            'value' => $this->value,
            'confidence' => $this->confidence,
            'probabilities' => $this->probabilities->toArray(),
        ];
    }
}
