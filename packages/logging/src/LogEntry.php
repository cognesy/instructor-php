<?php

declare(strict_types=1);

namespace Cognesy\Logging;

use DateTimeImmutable;
use JsonSerializable;
use Psr\Log\LogLevel;

/**
 * Immutable value object representing a log entry
 */
readonly class LogEntry implements JsonSerializable
{
    public function __construct(
        public string $level,
        public string $message,
        public array $context,
        public DateTimeImmutable $timestamp,
        public string $channel = 'default',
    ) {}

    public static function create(
        string $level,
        string $message,
        array $context = [],
        ?DateTimeImmutable $timestamp = null,
        string $channel = 'default',
    ): self {
        return new self(
            level: $level,
            message: $message,
            context: $context,
            timestamp: $timestamp ?? new DateTimeImmutable(),
            channel: $channel,
        );
    }

    public function withContext(array $additionalContext): self
    {
        return new self(
            level: $this->level,
            message: $this->message,
            context: array_merge($this->context, $additionalContext),
            timestamp: $this->timestamp,
            channel: $this->channel,
        );
    }

    public function withLevel(string $level): self
    {
        return new self(
            level: $level,
            message: $this->message,
            context: $this->context,
            timestamp: $this->timestamp,
            channel: $this->channel,
        );
    }

    public function withMessage(string $message): self
    {
        return new self(
            level: $this->level,
            message: $message,
            context: $this->context,
            timestamp: $this->timestamp,
            channel: $this->channel,
        );
    }

    public function withChannel(string $channel): self
    {
        return new self(
            level: $this->level,
            message: $this->message,
            context: $this->context,
            timestamp: $this->timestamp,
            channel: $channel,
        );
    }

    public function isLevel(string $level): bool
    {
        return $this->level === $level;
    }

    public function isLevelOrAbove(string $minimumLevel): bool
    {
        return $this->getLevelPriority($this->level) <= $this->getLevelPriority($minimumLevel);
    }

    public function jsonSerialize(): array
    {
        return [
            'level' => $this->level,
            'message' => $this->message,
            'context' => $this->context,
            'timestamp' => $this->timestamp->format(\DateTime::ISO8601),
            'channel' => $this->channel,
        ];
    }

    private function getLevelPriority(string $level): int
    {
        return match ($level) {
            LogLevel::EMERGENCY => 0,
            LogLevel::ALERT => 1,
            LogLevel::CRITICAL => 2,
            LogLevel::ERROR => 3,
            LogLevel::WARNING => 4,
            LogLevel::NOTICE => 5,
            LogLevel::INFO => 6,
            LogLevel::DEBUG => 7,
            default => 8,
        };
    }
}