<?php declare(strict_types=1);

namespace Cognesy\Agents\Telemetry;

use Cognesy\Telemetry\Domain\Continuation\TelemetryContinuation;
use Cognesy\Telemetry\Domain\Trace\TraceContext;

final readonly class AgentTelemetrySeed
{
    public function __construct(
        private ?TraceContext $trace = null,
        private ?string $rootOperationId = null,
        private ?string $parentOperationId = null,
        private ?string $sessionId = null,
        private ?string $userId = null,
        private ?string $conversationId = null,
        private ?string $requestId = null,
    ) {}

    public static function empty(): self
    {
        return new self();
    }

    public static function fromContinuation(TelemetryContinuation $continuation): self
    {
        $correlation = $continuation->correlation();

        return new self(
            trace: $continuation->context(),
            rootOperationId: self::stringValue($correlation, 'root_operation_id'),
            parentOperationId: self::stringValue($correlation, 'parent_operation_id'),
            sessionId: self::stringValue($correlation, 'session_id'),
            userId: self::stringValue($correlation, 'user_id'),
            conversationId: self::stringValue($correlation, 'conversation_id'),
            requestId: self::stringValue($correlation, 'request_id'),
        );
    }

    public function trace(): ?TraceContext {
        return $this->trace;
    }

    public function rootOperationId(): ?string {
        return $this->rootOperationId;
    }

    public function parentOperationId(): ?string {
        return $this->parentOperationId;
    }

    public function sessionId(): ?string {
        return $this->sessionId;
    }

    public function userId(): ?string {
        return $this->userId;
    }

    public function conversationId(): ?string {
        return $this->conversationId;
    }

    public function requestId(): ?string {
        return $this->requestId;
    }

    /** @return array{trace?: array{traceparent: string, tracestate?: string}, root_operation_id?: string, parent_operation_id?: string, session_id?: string, user_id?: string, conversation_id?: string, request_id?: string} */
    public function toArray(): array
    {
        $data = [];

        $data = match ($this->trace) {
            null => $data,
            default => [...$data, 'trace' => $this->trace->toArray()],
        };
        $data = match ($this->rootOperationId) {
            null => $data,
            default => [...$data, 'root_operation_id' => $this->rootOperationId],
        };
        $data = match ($this->parentOperationId) {
            null => $data,
            default => [...$data, 'parent_operation_id' => $this->parentOperationId],
        };
        $data = match ($this->sessionId) {
            null => $data,
            default => [...$data, 'session_id' => $this->sessionId],
        };
        $data = match ($this->userId) {
            null => $data,
            default => [...$data, 'user_id' => $this->userId],
        };
        $data = match ($this->conversationId) {
            null => $data,
            default => [...$data, 'conversation_id' => $this->conversationId],
        };

        return match ($this->requestId) {
            null => $data,
            default => [...$data, 'request_id' => $this->requestId],
        };
    }

    /**
     * @param array{
     *   trace?: array{traceparent: string, tracestate?: string},
     *   root_operation_id?: string,
     *   parent_operation_id?: string,
     *   session_id?: string,
     *   user_id?: string,
     *   conversation_id?: string,
     *   request_id?: string
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            trace: isset($data['trace']) ? TraceContext::fromArray($data['trace']) : null,
            rootOperationId: $data['root_operation_id'] ?? null,
            parentOperationId: $data['parent_operation_id'] ?? null,
            sessionId: $data['session_id'] ?? null,
            userId: $data['user_id'] ?? null,
            conversationId: $data['conversation_id'] ?? null,
            requestId: $data['request_id'] ?? null,
        );
    }

    /** @param array<string, scalar> $correlation */
    private static function stringValue(array $correlation, string $key): ?string
    {
        return match (true) {
            is_string($correlation[$key] ?? null) && $correlation[$key] !== '' => $correlation[$key],
            is_int($correlation[$key] ?? null), is_float($correlation[$key] ?? null) => (string) $correlation[$key],
            is_bool($correlation[$key] ?? null) => $correlation[$key] ? 'true' : 'false',
            default => null,
        };
    }
}
