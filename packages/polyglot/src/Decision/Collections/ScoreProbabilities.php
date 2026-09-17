<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Collections;

use Cognesy\Polyglot\Decision\Internal\DecisionData;
use InvalidArgumentException;

final readonly class ScoreProbabilities
{
    /** @var list<float> */
    private array $probabilities;

    private function __construct(float ...$probabilities)
    {
        if (count($probabilities) < 2) {
            throw new InvalidArgumentException('Score probabilities require at least two levels.');
        }
        DecisionData::assertProbabilityDistribution($probabilities, 'Score probabilities');
        $this->probabilities = array_values($probabilities);
    }

    public static function of(float ...$probabilities): self
    {
        return new self(...array_map(
            static fn (float $probability): float => DecisionData::probability($probability, 'Score probability'),
            $probabilities,
        ));
    }

    public static function fromArray(array $data): self
    {
        if (! array_is_list($data)) {
            throw new InvalidArgumentException('Score probabilities must be a list.');
        }

        return new self(...array_map(
            static fn (mixed $probability): float => DecisionData::probability($probability, 'Score probability'),
            $data,
        ));
    }

    /** @return list<float> */
    public function all(): array
    {
        return $this->probabilities;
    }

    public function count(): int
    {
        return count($this->probabilities);
    }

    public function at(int $level): float
    {
        return $this->probabilities[$level]
            ?? throw new InvalidArgumentException("Score probability level {$level} does not exist.");
    }

    public function expectedValue(): float
    {
        return array_sum(array_map(
            static fn (float $probability, int $level): float => $probability * $level,
            $this->probabilities,
            array_keys($this->probabilities),
        ));
    }

    /** @return list<float> */
    public function toArray(): array
    {
        return $this->probabilities;
    }
}
