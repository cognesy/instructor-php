<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Answers;

use Cognesy\Polyglot\Decision\Collections\ScoreLegend;
use Cognesy\Polyglot\Decision\Collections\ScoreProbabilities;
use Cognesy\Polyglot\Decision\Internal\DecisionData;
use InvalidArgumentException;

final readonly class ScoreAnswer
{
    private const float EXPECTED_VALUE_TOLERANCE = 0.02;

    private string $questionId;

    private float $value;

    private float $confidence;

    public function __construct(
        string $questionId,
        float $value,
        float $confidence,
        private ScoreProbabilities $probabilities,
        private ScoreLegend $legend,
    ) {
        $this->questionId = DecisionData::nonEmptyString($questionId, 'Score answer question ID');
        $this->value = DecisionData::finiteFloat($value, 'Score answer value');
        $this->confidence = DecisionData::probability($confidence, 'Score answer confidence');
        if ($probabilities->count() !== $legend->count()) {
            throw new InvalidArgumentException('Score probabilities and legend must have the same levels.');
        }
        if ($this->value < 0.0 || $this->value > $legend->count() - 1) {
            throw new InvalidArgumentException('Score answer value is outside the legend range.');
        }
        if (abs($this->value - $probabilities->expectedValue()) > self::EXPECTED_VALUE_TOLERANCE) {
            throw new InvalidArgumentException('Score answer value must match the probability-weighted level.');
        }
    }

    public static function fromArray(array $data): self
    {
        DecisionData::assertKnownFields(
            $data,
            ['id', 'type', 'value', 'confidence', 'probabilities', 'legend'],
            'Score answer',
        );
        if (($data['type'] ?? null) !== 'score') {
            throw new InvalidArgumentException('Score answer type must be score.');
        }
        if (! is_array($data['probabilities'] ?? null) || ! is_array($data['legend'] ?? null)) {
            throw new InvalidArgumentException('Score answer probabilities and legend must be lists.');
        }

        return new self(
            questionId: DecisionData::nonEmptyString($data['id'] ?? null, 'Score answer question ID'),
            value: DecisionData::finiteFloat($data['value'] ?? null, 'Score answer value'),
            confidence: DecisionData::probability($data['confidence'] ?? null, 'Score answer confidence'),
            probabilities: ScoreProbabilities::fromArray($data['probabilities']),
            legend: ScoreLegend::fromArray($data['legend']),
        );
    }

    public function questionId(): string
    {
        return $this->questionId;
    }

    public function value(): float
    {
        return $this->value;
    }

    public function confidence(): float
    {
        return $this->confidence;
    }

    public function probabilities(): ScoreProbabilities
    {
        return $this->probabilities;
    }

    public function legend(): ScoreLegend
    {
        return $this->legend;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->questionId,
            'type' => 'score',
            'value' => $this->value,
            'confidence' => $this->confidence,
            'probabilities' => $this->probabilities->toArray(),
            'legend' => $this->legend->toArray(),
        ];
    }
}
