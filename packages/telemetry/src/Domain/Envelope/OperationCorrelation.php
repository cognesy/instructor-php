<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Envelope;

final readonly class OperationCorrelation
{
    public function __construct(
        private string $rootOperationId,
        private ?string $parentOperationId = null,
        private ?string $sessionId = null,
        private ?string $userId = null,
        private ?string $conversationId = null,
        private ?string $requestId = null,
    ) {}

    public static function root(
        string $operationId,
        ?string $sessionId = null,
        ?string $userId = null,
        ?string $conversationId = null,
        ?string $requestId = null,
    ): self {
        return new self($operationId, null, $sessionId, $userId, $conversationId, $requestId);
    }

    public static function child(
        string $rootOperationId,
        string $parentOperationId,
        ?string $sessionId = null,
        ?string $userId = null,
        ?string $conversationId = null,
        ?string $requestId = null,
    ): self {
        return new self($rootOperationId, $parentOperationId, $sessionId, $userId, $conversationId, $requestId);
    }

    public function rootOperationId(): string
    {
        return $this->rootOperationId;
    }

    public function parentOperationId(): ?string
    {
        return $this->parentOperationId;
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    public function userId(): ?string
    {
        return $this->userId;
    }

    public function conversationId(): ?string
    {
        return $this->conversationId;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    /** @return array{root_operation_id: string, parent_operation_id?: string, session_id?: string, user_id?: string, conversation_id?: string, request_id?: string} */
    public function toArray(): array
    {
        $data = ['root_operation_id' => $this->rootOperationId];

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
     *   root_operation_id: string,
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
            rootOperationId: $data['root_operation_id'],
            parentOperationId: $data['parent_operation_id'] ?? null,
            sessionId: $data['session_id'] ?? null,
            userId: $data['user_id'] ?? null,
            conversationId: $data['conversation_id'] ?? null,
            requestId: $data['request_id'] ?? null,
        );
    }
}
