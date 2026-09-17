<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Collections;

use Cognesy\Polyglot\Decision\Internal\DecisionData;
use InvalidArgumentException;

final readonly class ChoiceProbabilities
{
    /** @var list<array{id: string, probability: float}> */
    private array $probabilities;

    /** @var array<string, float> */
    private array $byId;

    /** @param list<array{id: string, probability: float}> $probabilities */
    private function __construct(array $probabilities)
    {
        $byId = [];
        foreach ($probabilities as $entry) {
            $key = self::key($entry['id']);
            if (array_key_exists($key, $byId)) {
                throw new InvalidArgumentException('Choice probability IDs must be unique.');
            }
            $byId[$key] = $entry['probability'];
        }
        DecisionData::assertProbabilityDistribution(array_values($byId), 'Choice probabilities');
        $this->probabilities = $probabilities;
        $this->byId = $byId;
    }

    /** @param array{0: string, 1: int|float} ...$probabilities */
    public static function of(array ...$probabilities): self
    {
        return new self(array_map(
            static fn (array $entry): array => [
                'id' => DecisionData::nonEmptyString($entry[0] ?? null, 'Choice probability ID'),
                'probability' => DecisionData::probability($entry[1] ?? null, 'Choice probability'),
            ],
            $probabilities,
        ));
    }

    public static function fromArray(array $data): self
    {
        if (! array_is_list($data)) {
            throw new InvalidArgumentException('Choice probabilities must be a list.');
        }

        return new self(array_map(static function (mixed $entry): array {
            if (! is_array($entry)) {
                throw new InvalidArgumentException('Each Choice probability must be an object.');
            }
            DecisionData::assertKnownFields($entry, ['id', 'probability'], 'Choice probability');

            return [
                'id' => DecisionData::nonEmptyString($entry['id'] ?? null, 'Choice probability ID'),
                'probability' => DecisionData::probability($entry['probability'] ?? null, 'Choice probability'),
            ];
        }, $data));
    }

    /** @return list<array{id: string, probability: float}> */
    public function all(): array
    {
        return $this->probabilities;
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_column($this->probabilities, 'id');
    }

    public function has(string $id): bool
    {
        return array_key_exists(self::key($id), $this->byId);
    }

    public function probability(string $id): float
    {
        return $this->byId[self::key($id)]
            ?? throw new InvalidArgumentException("Choice probability '{$id}' does not exist.");
    }

    public function highest(): float
    {
        return max($this->byId);
    }

    public function toArray(): array
    {
        return $this->probabilities;
    }

    private static function key(string $id): string
    {
        return '#'.$id;
    }
}
