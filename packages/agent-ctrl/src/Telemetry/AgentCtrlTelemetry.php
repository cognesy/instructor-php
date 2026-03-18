<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Telemetry;

use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\Telemetry\Domain\Continuation\TelemetryContinuation;
use Cognesy\Telemetry\Domain\Trace\TraceContext;

final readonly class AgentCtrlTelemetry
{
    /** @return array<string, scalar> */
    public static function correlationForExecution(string $executionId): array
    {
        return ['agent_ctrl.execution_id' => $executionId];
    }

    /** @return array<string, scalar> */
    public static function correlationForResponse(AgentResponse $response): array
    {
        $correlation = self::correlationForExecution((string) $response->executionId());
        $sessionId = $response->sessionId();

        return match ($sessionId) {
            null => $correlation,
            default => [...$correlation, 'agent_ctrl.session_id' => (string) $sessionId],
        };
    }

    public static function continuationForResponse(
        AgentResponse $response,
        TraceContext $context,
    ): TelemetryContinuation {
        return new TelemetryContinuation(
            context: $context,
            correlation: self::correlationForResponse($response),
        );
    }

    public static function continuationFromResponse(AgentResponse $response, TraceContext $context): TelemetryContinuation
    {
        return self::continuationForResponse($response, $context);
    }
}
