<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Data;

use DateTimeImmutable;

/**
 * Result of storing messages to a storage backend.
 */
final readonly class StoreMessagesResult
{
    public function __construct(
        public string $sessionId,
        public bool $success,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $finishedAt,
        public int $sectionsStored,
        public int $messagesStored,
        public int $newMessages,
        public ?string $errorMessage = null,
    ) {}

    public static function success(
        string $sessionId,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $finishedAt,
        int $sectionsStored,
        int $messagesStored,
        int $newMessages = 0,
    ): self {
        return new self(
            sessionId: $sessionId,
            success: true,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            sectionsStored: $sectionsStored,
            messagesStored: $messagesStored,
            newMessages: $newMessages,
        );
    }

    public static function failure(
        string $sessionId,
        DateTimeImmutable $startedAt,
        string $errorMessage,
    ): self {
        return new self(
            sessionId: $sessionId,
            success: false,
            startedAt: $startedAt,
            finishedAt: new DateTimeImmutable(),
            sectionsStored: 0,
            messagesStored: 0,
            newMessages: 0,
            errorMessage: $errorMessage,
        );
    }

    public function durationMs(): float {
        $start = (float) $this->startedAt->format('U.u');
        $end = (float) $this->finishedAt->format('U.u');
        return ($end - $start) * 1000;
    }

    public function isSuccess(): bool {
        return $this->success;
    }

    public function isFailure(): bool {
        return !$this->success;
    }

    public function toArray(): array {
        return [
            'sessionId' => $this->sessionId,
            'success' => $this->success,
            'startedAt' => $this->startedAt->format(DateTimeImmutable::ATOM),
            'finishedAt' => $this->finishedAt->format(DateTimeImmutable::ATOM),
            'durationMs' => $this->durationMs(),
            'sectionsStored' => $this->sectionsStored,
            'messagesStored' => $this->messagesStored,
            'newMessages' => $this->newMessages,
            'errorMessage' => $this->errorMessage,
        ];
    }
}
