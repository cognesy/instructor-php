<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Answers;

use Cognesy\Polyglot\Decision\Internal\DecisionData;
use InvalidArgumentException;

final readonly class NoulAnswer
{
    private string $questionId;

    private float $probability;

    private AnswerSignals $signals;

    public function __construct(
        string $questionId,
        float $probability,
        ?AnswerSignals $signals = null,
    )
    {
        $this->questionId = DecisionData::nonEmptyString($questionId, 'Noul answer question ID');
        $this->probability = DecisionData::probability($probability, 'Noul answer probability');
        $this->signals = $signals ?? AnswerSignals::empty();
    }

    public static function fromArray(array $data): self
    {
        DecisionData::assertKnownFields($data, ['id', 'type', 'probability', 'signals'], 'Noul answer');
        if (($data['type'] ?? null) !== 'noul') {
            throw new InvalidArgumentException('Noul answer type must be noul.');
        }

        return new self(
            questionId: DecisionData::nonEmptyString($data['id'] ?? null, 'Noul answer question ID'),
            probability: DecisionData::probability($data['probability'] ?? null, 'Noul answer probability'),
            signals: self::signalsFrom($data),
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

    public function confidence(): float
    {
        return max($this->probability, 1.0 - $this->probability);
    }

    public function signals(): AnswerSignals
    {
        return $this->signals;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->questionId,
            'type' => 'noul',
            'probability' => $this->probability,
            ...match ($this->signals->isEmpty()) {
                true => [],
                false => ['signals' => $this->signals->toArray()],
            },
        ];
    }

    private static function signalsFrom(array $data): AnswerSignals
    {
        if (! array_key_exists('signals', $data)) {
            return AnswerSignals::empty();
        }

        $signals = $data['signals'];
        if (! is_array($signals) || array_is_list($signals)) {
            throw new InvalidArgumentException('Noul answer signals must be an object.');
        }

        return AnswerSignals::fromArray($signals);
    }
}
