<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Collections;

use Cognesy\Polyglot\Decision\Data\JsonContent;
use InvalidArgumentException;
use stdClass;

final readonly class ScoreLegend
{
    /** @var list<JsonContent> */
    private array $descriptions;

    private function __construct(JsonContent ...$descriptions)
    {
        if (count($descriptions) < 2) {
            throw new InvalidArgumentException('Score legend requires at least two levels.');
        }
        $this->descriptions = array_values($descriptions);
    }

    public static function of(string|JsonContent ...$descriptions): self
    {
        return new self(...array_map(
            static fn (string|JsonContent $description): JsonContent => is_string($description)
                ? JsonContent::text($description)
                : $description,
            $descriptions,
        ));
    }

    public static function fromArray(array $data): self
    {
        if (! array_is_list($data)) {
            throw new InvalidArgumentException('Score legend must be a list.');
        }

        return new self(...array_map(
            static fn (mixed $description): JsonContent => match (true) {
                is_string($description), is_array($description), $description instanceof stdClass => JsonContent::from($description),
                default => throw new InvalidArgumentException('Each Score legend entry must be text, an object, or a list.'),
            },
            $data,
        ));
    }

    public function count(): int
    {
        return count($this->descriptions);
    }

    public function at(int $level): JsonContent
    {
        return $this->descriptions[$level]
            ?? throw new InvalidArgumentException("Score legend level {$level} does not exist.");
    }

    public function toArray(): array
    {
        return array_map(
            static fn (JsonContent $description): string|array|stdClass => $description->value(),
            $this->descriptions,
        );
    }
}
