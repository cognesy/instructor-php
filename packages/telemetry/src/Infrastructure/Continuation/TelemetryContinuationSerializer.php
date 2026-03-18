<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Infrastructure\Continuation;

use Cognesy\Telemetry\Domain\Continuation\TelemetryContinuation;
use Cognesy\Telemetry\Domain\Trace\TraceContext;
use Cognesy\Utils\Json\Json;

final readonly class TelemetryContinuationSerializer
{
    public function encode(TelemetryContinuation $continuation): string {
        return Json::encode($continuation->toArray());
    }

    public function decode(string $json): TelemetryContinuation {
        /** @var array{traceparent: string, tracestate?: string|null, correlation?: array<string, scalar>} $data */
        $data = Json::decode($json);

        return TelemetryContinuation::fromArray($data);
    }
}
