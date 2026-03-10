<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Result event — final event with status and stats
 *
 * Example: {"type":"result","timestamp":"...","status":"success","stats":{"total_tokens":100,"input_tokens":40,"output_tokens":60,"cached":0,"duration_ms":1234,"tool_calls":2}}
 */
final readonly class ResultEvent extends StreamEvent
{
    public function __construct(
        array $rawData,
        public string $status,
        public ?array $error,
        public array $stats,
        public string $timestamp,
    ) {
        parent::__construct($rawData);
    }

    #[\Override]
    public function type(): string
    {
        return 'result';
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isError(): bool
    {
        return $this->status === 'error';
    }

    public function inputTokens(): int
    {
        return Normalize::toInt($this->stats['input_tokens'] ?? 0);
    }

    public function outputTokens(): int
    {
        return Normalize::toInt($this->stats['output_tokens'] ?? 0);
    }

    public function cachedTokens(): int
    {
        return Normalize::toInt($this->stats['cached'] ?? 0);
    }

    public function totalTokens(): int
    {
        return Normalize::toInt($this->stats['total_tokens'] ?? 0);
    }

    public function durationMs(): int
    {
        return Normalize::toInt($this->stats['duration_ms'] ?? 0);
    }

    public function toolCallCount(): int
    {
        return Normalize::toInt($this->stats['tool_calls'] ?? 0);
    }

    public static function fromArray(array $data): self
    {
        $error = $data['error'] ?? null;

        return new self(
            rawData: $data,
            status: Normalize::toString($data['status'] ?? ''),
            error: is_array($error) ? $error : null,
            stats: Normalize::toArray($data['stats'] ?? []),
            timestamp: Normalize::toString($data['timestamp'] ?? ''),
        );
    }
}
