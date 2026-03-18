<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Application\Registry;

use Cognesy\Telemetry\Domain\Trace\SpanReference;
use Cognesy\Telemetry\Domain\Value\AttributeBag;
use DateTimeImmutable;

final readonly class ActiveSpan
{
    public function __construct(
        private string $key,
        private SpanReference $reference,
        private string $name,
        private DateTimeImmutable $startedAt,
        private AttributeBag $attributes,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function reference(): SpanReference
    {
        return $this->reference;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function attributes(): AttributeBag
    {
        return $this->attributes;
    }
}
