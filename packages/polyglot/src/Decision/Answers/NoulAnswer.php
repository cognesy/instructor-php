<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Answers;

use Cognesy\Polyglot\Decision\Collections\NoulProbabilities;
use Cognesy\Polyglot\Decision\Internal\DecisionData;
use InvalidArgumentException;

final readonly class NoulAnswer
{
    private string $questionId;

    private NoulProbabilities $probabilities;

    private AnswerSignals $signals;

    public function __construct(
        string $questionId,
        float $probability,
        ?AnswerSignals $signals = null,
        ?NoulProbabilities $probabilities = null,
    ) {
        $this->questionId = DecisionData::nonEmptyString($questionId, 'Noul answer question ID');
        $probability = DecisionData::probability($probability, 'Noul answer probability');
        $this->probabilities = $probabilities ?? NoulProbabilities::binary($probability);
        if (abs($this->probabilities->positive() - $probability) > 0.000000001) {
            throw new InvalidArgumentException('Noul answer probability must match its positive probability.');
        }
        $this->signals = $signals ?? AnswerSignals::empty();
    }

    public static function fromProbabilities(
        string $questionId,
        NoulProbabilities $probabilities,
        ?AnswerSignals $signals = null,
    ): self {
        return new self(
            questionId: $questionId,
            probability: $probabilities->positive(),
            signals: $signals,
            probabilities: $probabilities,
        );
    }

    public static function fromArray(array $data): self
    {
        DecisionData::assertKnownFields(
            $data,
            ['id', 'type', 'probability', 'probabilities', 'signals'],
            'Noul answer',
        );
        if (($data['type'] ?? null) !== 'noul') {
            throw new InvalidArgumentException('Noul answer type must be noul.');
        }

        $probability = DecisionData::probability($data['probability'] ?? null, 'Noul answer probability');

        return new self(
            questionId: DecisionData::nonEmptyString($data['id'] ?? null, 'Noul answer question ID'),
            probability: $probability,
            signals: self::signalsFrom($data),
            probabilities: self::probabilitiesFrom($data, $probability),
        );
    }

    public function questionId(): string
    {
        return $this->questionId;
    }

    public function probability(): float
    {
        return $this->probabilities->positive();
    }

    public function confidence(): float
    {
        return $this->probabilities->confidence();
    }

    public function probabilities(): NoulProbabilities
    {
        return $this->probabilities;
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
            'probability' => $this->probability(),
            ...match ($this->probabilities->isBinary()) {
                true => [],
                false => ['probabilities' => $this->probabilities->toArray()],
            },
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

    private static function probabilitiesFrom(array $data, float $probability): NoulProbabilities
    {
        if (! array_key_exists('probabilities', $data)) {
            return NoulProbabilities::binary($probability);
        }

        $probabilities = $data['probabilities'];
        if (! is_array($probabilities) || array_is_list($probabilities)) {
            throw new InvalidArgumentException('Noul answer probabilities must be an object.');
        }

        return NoulProbabilities::fromArray($probabilities);
    }
}
