<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Collections;

use Cognesy\Polyglot\Decision\Data\JsonContent;
use InvalidArgumentException;
use stdClass;

final readonly class ScoreLevels
{
    /** @var list<JsonContent> */
    private array $levels;

    private function __construct(JsonContent ...$levels)
    {
        if (count($levels) < 2) {
            throw new InvalidArgumentException('Score requires at least two levels.');
        }
        $this->levels = array_values($levels);
    }

    public static function of(string|JsonContent ...$levels): self
    {
        return new self(...array_map(
            static fn (string|JsonContent $level): JsonContent => is_string($level) ? JsonContent::text($level) : $level,
            $levels,
        ));
    }

    public static function fromArray(array $data): self
    {
        if (! array_is_list($data)) {
            throw new InvalidArgumentException('Score levels must be a list.');
        }
        $levels = array_map(
            static fn (mixed $level): JsonContent => match (true) {
                is_string($level), is_array($level), $level instanceof stdClass => JsonContent::from($level),
                default => throw new InvalidArgumentException('Each Score level must be text, an object, or a list.'),
            },
            $data,
        );

        return new self(...$levels);
    }

    /** @return list<JsonContent> */
    public function all(): array
    {
        return $this->levels;
    }

    public function count(): int
    {
        return count($this->levels);
    }

    public function at(int $index): JsonContent
    {
        return $this->levels[$index]
            ?? throw new InvalidArgumentException("Score level {$index} does not exist.");
    }

    public function toArray(): array
    {
        return array_map(static fn (JsonContent $level): string|array|stdClass => $level->value(), $this->levels);
    }
}
