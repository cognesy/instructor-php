<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Application\Registry;

use Cognesy\Telemetry\Domain\Trace\SpanReference;
use Cognesy\Telemetry\Domain\Trace\TraceContext;
use Cognesy\Telemetry\Domain\Value\AttributeBag;
use DateTimeImmutable;

final class TraceRegistry
{
    /** @var array<string, ActiveSpan> */
    private array $spans = [];

    public function openRoot(
        string $key,
        string $name,
        ?TraceContext $context = null,
        ?AttributeBag $attributes = null,
    ): ActiveSpan {
        $reference = match ($context) {
            null => SpanReference::fromContext(TraceContext::fresh()),
            default => SpanReference::childOf(SpanReference::fromContext($context)),
        };
        $span = new ActiveSpan(
            key: $key,
            reference: $reference,
            name: $name,
            startedAt: new DateTimeImmutable(),
            attributes: $attributes ?? AttributeBag::empty(),
        );
        $this->spans[$key] = $span;
        return $span;
    }

    public function openChild(
        string $key,
        string $parentKey,
        string $name,
        ?AttributeBag $attributes = null,
    ): ActiveSpan {
        $parent = $this->require($parentKey);
        $span = new ActiveSpan(
            key: $key,
            reference: SpanReference::childOf($parent->reference()),
            name: $name,
            startedAt: new DateTimeImmutable(),
            attributes: $attributes ?? AttributeBag::empty(),
        );
        $this->spans[$key] = $span;
        return $span;
    }

    public function has(string $key): bool {
        return isset($this->spans[$key]);
    }

    public function get(string $key): ?ActiveSpan {
        return $this->spans[$key] ?? null;
    }

    public function spanReference(string $key): ?SpanReference
    {
        return isset($this->spans[$key]) ? $this->spans[$key]->reference() : null;
    }

    public function close(string $key): ?ActiveSpan {
        $span = $this->spans[$key] ?? null;
        unset($this->spans[$key]);
        return $span;
    }

    private function require(string $key): ActiveSpan {
        return $this->spans[$key]
            ?? throw new \InvalidArgumentException("Missing span reference for key: {$key}");
    }
}
