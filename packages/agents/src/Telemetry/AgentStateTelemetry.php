<?php declare(strict_types=1);

namespace Cognesy\Agents\Telemetry;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Telemetry\Domain\Continuation\TelemetryContinuation;

final readonly class AgentStateTelemetry
{
    public const METADATA_KEY = '_telemetry.continuation';
    public const SEED_METADATA_KEY = '_telemetry.seed';

    public static function storeContinuation(
        AgentState $state,
        TelemetryContinuation $continuation,
    ): AgentState {
        return self::storeSeed(
            $state->withMetadata(self::METADATA_KEY, $continuation->toArray()),
            AgentTelemetrySeed::fromContinuation($continuation),
        );
    }

    public static function loadContinuation(AgentState $state): ?TelemetryContinuation
    {
        $seed = self::loadSeed($state);
        if ($seed?->trace() !== null) {
            return new TelemetryContinuation(
                context: $seed->trace(),
                correlation: array_filter([
                    'root_operation_id' => $seed->rootOperationId(),
                    'parent_operation_id' => $seed->parentOperationId(),
                    'session_id' => $seed->sessionId(),
                    'user_id' => $seed->userId(),
                    'conversation_id' => $seed->conversationId(),
                    'request_id' => $seed->requestId(),
                ], static fn(mixed $value): bool => $value !== null),
            );
        }

        $payload = $state->metadata()->get(self::METADATA_KEY);

        return match (true) {
            is_array($payload) && isset($payload['traceparent']) => TelemetryContinuation::fromArray($payload),
            default => null,
        };
    }

    public static function storeSeed(AgentState $state, AgentTelemetrySeed $seed): AgentState
    {
        return $state->withMetadata(self::SEED_METADATA_KEY, $seed->toArray());
    }

    public static function loadSeed(AgentState $state): ?AgentTelemetrySeed
    {
        $payload = $state->metadata()->get(self::SEED_METADATA_KEY);
        if (is_array($payload)) {
            return AgentTelemetrySeed::fromArray($payload);
        }

        $continuationPayload = $state->metadata()->get(self::METADATA_KEY);

        return match (true) {
            is_array($continuationPayload) && isset($continuationPayload['traceparent']) => AgentTelemetrySeed::fromContinuation(
                TelemetryContinuation::fromArray($continuationPayload)
            ),
            default => null,
        };
    }

    public static function storeSessionContinuation(
        AgentSession $session,
        TelemetryContinuation $continuation,
    ): AgentSession {
        return $session->withState(self::storeContinuation($session->state(), $continuation));
    }

    public static function loadSessionContinuation(AgentSession $session): ?TelemetryContinuation
    {
        return self::loadContinuation($session->state());
    }
}
