<?php declare(strict_types=1);

namespace Cognesy\Metrics\Data;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Counter - monotonically increasing value.
 *
 * Counters track cumulative totals that only increase,
 * like request counts, errors, or bytes transferred.
 */
final readonly class Counter implements Metric
{
    public function __construct(
        private string $name,
        private float $value,
        private Tags $tags,
        private DateTimeImmutable $timestamp,
    ) {
        if ($value < 0) {
            throw new InvalidArgumentException('Counter value must be non-negative');
        }
    }

    public static function create(
        string $name,
        float $value = 1,
        array $tags = [],
        ?DateTimeImmutable $timestamp = null,
    ): self {
        return new self(
            $name,
            $value,
            Tags::of($tags),
            $timestamp ?? new DateTimeImmutable(),
        );
    }

    public function name(): string {
        return $this->name;
    }

    public function value(): float {
        return $this->value;
    }

    public function tags(): Tags {
        return $this->tags;
    }

    public function timestamp(): DateTimeImmutable {
        return $this->timestamp;
    }

    public function type(): string {
        return 'counter';
    }

    public function toArray(): array {
        return [
            'type' => $this->type(),
            'name' => $this->name,
            'value' => $this->value,
            'tags' => $this->tags->toArray(),
            'timestamp' => $this->timestamp->format('c'),
        ];
    }

    public function jsonSerialize(): array {
        return $this->toArray();
    }

    public function __toString(): string {
        $tagsStr = (string) $this->tags;
        $tagsFormatted = $tagsStr !== '' ? "{{$tagsStr}}" : '';
        return sprintf('%s%s %g', $this->name, $tagsFormatted, $this->value);
    }
}
