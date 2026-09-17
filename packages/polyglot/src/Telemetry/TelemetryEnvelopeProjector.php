<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Telemetry;

use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Envelope\OperationKind;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelopeAttributes;
use Cognesy\Telemetry\Domain\Value\AttributeBag;

final readonly class TelemetryEnvelopeProjector
{
    public function __construct(private Telemetry $telemetry) {}

    /** @param array<string, scalar|array<array-key, scalar>|null> $items */
    public function attributes(array $items): AttributeBag
    {
        return AttributeBag::fromArray(array_filter($items, static fn (mixed $value): bool => $value !== null));
    }

    public function open(TelemetryEnvelope $envelope, AttributeBag $attributes): void
    {
        $operation = $envelope->operation();
        if ($this->telemetry->spanReference($operation->id()) !== null) {
            return;
        }

        $correlation = $envelope->correlation();
        $parentKey = $correlation->parentOperationId() ?? $correlation->rootOperationId();
        $rootKey = $correlation->rootOperationId();
        $resolvedParent = match (true) {
            $this->telemetry->spanReference($parentKey) !== null => $parentKey,
            $parentKey !== $rootKey && $this->telemetry->spanReference($rootKey) !== null => $rootKey,
            default => null,
        };
        $attributes = TelemetryEnvelopeAttributes::fromEnvelope($envelope)->merge($attributes);

        match ($operation->kind()) {
            OperationKind::RootSpan => $this->telemetry->openRoot(
                key: $operation->id(),
                name: $operation->name(),
                context: $envelope->trace(),
                attributes: $attributes,
            ),
            OperationKind::Span => match ($resolvedParent) {
                null => $this->telemetry->openRoot(
                    key: $operation->id(),
                    name: $operation->name(),
                    context: $envelope->trace(),
                    attributes: $attributes,
                ),
                default => $this->telemetry->openChild(
                    key: $operation->id(),
                    parentKey: $resolvedParent,
                    name: $operation->name(),
                    attributes: $attributes,
                ),
            },
            OperationKind::Event => match ($resolvedParent) {
                null => null,
                default => $this->telemetry->log(
                    key: $resolvedParent,
                    name: $operation->name(),
                    attributes: $attributes,
                ),
            },
            default => null,
        };
    }

    public function complete(TelemetryEnvelope $envelope, AttributeBag $attributes): void
    {
        $this->telemetry->complete(
            $envelope->operation()->id(),
            TelemetryEnvelopeAttributes::fromEnvelope($envelope)->merge($attributes),
        );
    }

    public function fail(TelemetryEnvelope $envelope, AttributeBag $attributes): void
    {
        $this->telemetry->fail(
            $envelope->operation()->id(),
            TelemetryEnvelopeAttributes::fromEnvelope($envelope)->merge($attributes),
        );
    }
}
