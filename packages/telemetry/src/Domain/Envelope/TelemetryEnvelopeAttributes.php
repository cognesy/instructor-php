<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Envelope;

use Cognesy\Telemetry\Domain\Value\AttributeBag;
use Cognesy\Utils\Json\Json;

final readonly class TelemetryEnvelopeAttributes
{
    public static function fromEnvelope(TelemetryEnvelope $envelope): AttributeBag
    {
        return AttributeBag::fromArray(array_filter([
            'telemetry.operation.id' => $envelope->operation()->id(),
            'telemetry.operation.type' => $envelope->operation()->type(),
            'telemetry.operation.name' => $envelope->operation()->name(),
            'telemetry.operation.kind' => $envelope->operation()->kind()->value,
            'telemetry.root_operation_id' => $envelope->correlation()->rootOperationId(),
            'telemetry.parent_operation_id' => $envelope->correlation()->parentOperationId(),
            'telemetry.session_id' => $envelope->correlation()->sessionId(),
            'telemetry.user_id' => $envelope->correlation()->userId(),
            'telemetry.conversation_id' => $envelope->correlation()->conversationId(),
            'telemetry.request_id' => $envelope->correlation()->requestId(),
            'telemetry.io.input' => self::serialize($envelope->io()?->input()),
            'telemetry.io.output' => self::serialize($envelope->io()?->output()),
            'telemetry.tags' => $envelope->tags(),
            'telemetry.metadata' => self::serialize($envelope->metadata()),
        ], static fn(mixed $value): bool => $value !== null && $value !== [] && $value !== ''));
    }

    private static function serialize(mixed $value): string|int|float|bool|array|null
    {
        return match (true) {
            $value === null => null,
            is_string($value), is_int($value), is_float($value), is_bool($value) => $value,
            is_array($value) && self::isScalarList($value) => $value,
            default => Json::encode($value),
        };
    }

    /** @param array<array-key, mixed> $value */
    private static function isScalarList(array $value): bool
    {
        foreach ($value as $item) {
            if (is_scalar($item) || $item === null) {
                continue;
            }

            return false;
        }

        return true;
    }
}
