<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Lifecycle;

/**
 * Decision returned by stopping() observer method.
 *
 * Can allow the stop or prevent it to force continuation.
 */
final readonly class StopDecision
{
    private function __construct(
        private bool $prevented,
        private ?string $reason,
    ) {}

    public static function allow(): self
    {
        return new self(
            prevented: false,
            reason: null,
        );
    }

    public static function prevent(string $reason): self
    {
        return new self(
            prevented: true,
            reason: $reason,
        );
    }

    public function isAllowed(): bool
    {
        return !$this->prevented;
    }

    public function isPrevented(): bool
    {
        return $this->prevented;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
