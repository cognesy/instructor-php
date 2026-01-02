<?php declare(strict_types=1);

namespace Cognesy\Metrics\Data;

use DateTimeImmutable;

/**
 * Gauge - point-in-time value that can go up or down.
 *
 * Gauges track instantaneous values like current memory usage,
 * active connections, queue depth, or temperature.
 */
final readonly class Gauge implements Metric
{
    public function __construct(
        private string $name,
        private float $value,
        private Tags $tags,
        private DateTimeImmutable $timestamp,
    ) {}

    public static function create(
        string $name,
        float $value,
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
        return 'gauge';
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
