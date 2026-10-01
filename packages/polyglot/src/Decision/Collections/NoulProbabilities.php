<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Collections;

use Cognesy\Polyglot\Decision\Internal\DecisionData;

final readonly class NoulProbabilities
{
    private function __construct(
        private float $positive,
        private float $negative,
        private float $unknown,
    ) {
        DecisionData::assertProbabilityDistribution(
            [$positive, $negative, $unknown],
            'Noul probabilities',
        );
    }

    public static function binary(float $positive): self
    {
        $positive = DecisionData::probability($positive, 'Noul positive probability');

        return new self($positive, 1.0 - $positive, 0.0);
    }

    public static function of(float $positive, float $negative, float $unknown): self
    {
        return new self(
            DecisionData::probability($positive, 'Noul positive probability'),
            DecisionData::probability($negative, 'Noul negative probability'),
            DecisionData::probability($unknown, 'Noul unknown probability'),
        );
    }

    public static function fromArray(array $data): self
    {
        DecisionData::assertKnownFields($data, ['positive', 'negative', 'unknown'], 'Noul probabilities');

        return self::of(
            positive: DecisionData::probability($data['positive'] ?? null, 'Noul positive probability'),
            negative: DecisionData::probability($data['negative'] ?? null, 'Noul negative probability'),
            unknown: DecisionData::probability($data['unknown'] ?? null, 'Noul unknown probability'),
        );
    }

    public function positive(): float
    {
        return $this->positive;
    }

    public function negative(): float
    {
        return $this->negative;
    }

    public function unknown(): float
    {
        return $this->unknown;
    }

    public function confidence(): float
    {
        return max($this->positive, $this->negative);
    }

    public function isBinary(): bool
    {
        return $this->unknown === 0.0
            && abs($this->negative - (1.0 - $this->positive)) <= 0.000000001;
    }

    /** @return array{positive: float, negative: float, unknown: float} */
    public function toArray(): array
    {
        return [
            'positive' => $this->positive,
            'negative' => $this->negative,
            'unknown' => $this->unknown,
        ];
    }
}
