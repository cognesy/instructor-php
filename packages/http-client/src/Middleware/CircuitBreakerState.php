<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware;

final readonly class CircuitBreakerState
{
    public const string STATE_CLOSED = 'closed';
    public const string STATE_OPEN = 'open';
    public const string STATE_HALF_OPEN = 'half_open';

    public function __construct(
        public string $state = self::STATE_CLOSED,
        public int $failures = 0,
        public int $lastFailure = 0,
        public int $halfOpenRequests = 0,
        public int $halfOpenSuccesses = 0,
    ) {}

    public static function fresh(): self
    {
        return new self();
    }

    public static function fromArray(array $state): ?self
    {
        $name = $state['state'] ?? null;
        $failures = $state['failures'] ?? null;
        $lastFailure = $state['lastFailure'] ?? null;
        $halfOpenRequests = $state['halfOpenRequests'] ?? null;
        $halfOpenSuccesses = $state['halfOpenSuccesses'] ?? null;

        if (!is_string($name) || !self::isValidState($name)) {
            return null;
        }

        if (!is_int($failures) || !is_int($lastFailure) || !is_int($halfOpenRequests) || !is_int($halfOpenSuccesses)) {
            return null;
        }

        return new self(
            state: $name,
            failures: $failures,
            lastFailure: $lastFailure,
            halfOpenRequests: $halfOpenRequests,
            halfOpenSuccesses: $halfOpenSuccesses,
        );
    }

    /**
     * @return array{state: string, failures: int, lastFailure: int, halfOpenRequests: int, halfOpenSuccesses: int}
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'failures' => $this->failures,
            'lastFailure' => $this->lastFailure,
            'halfOpenRequests' => $this->halfOpenRequests,
            'halfOpenSuccesses' => $this->halfOpenSuccesses,
        ];
    }

    public function isOpen(): bool
    {
        return $this->state === self::STATE_OPEN;
    }

    public function isHalfOpen(): bool
    {
        return $this->state === self::STATE_HALF_OPEN;
    }

    public function asHalfOpen(): self
    {
        return new self(
            state: self::STATE_HALF_OPEN,
            failures: $this->failures,
            lastFailure: $this->lastFailure,
            halfOpenRequests: 0,
            halfOpenSuccesses: 0,
        );
    }

    public function asClosed(): self
    {
        return new self(
            state: self::STATE_CLOSED,
            failures: 0,
            lastFailure: $this->lastFailure,
            halfOpenRequests: 0,
            halfOpenSuccesses: 0,
        );
    }

    public function withState(string $state): self
    {
        return new self(
            state: $state,
            failures: $this->failures,
            lastFailure: $this->lastFailure,
            halfOpenRequests: $this->halfOpenRequests,
            halfOpenSuccesses: $this->halfOpenSuccesses,
        );
    }

    public function withFailures(int $failures): self
    {
        return new self(
            state: $this->state,
            failures: max(0, $failures),
            lastFailure: $this->lastFailure,
            halfOpenRequests: $this->halfOpenRequests,
            halfOpenSuccesses: $this->halfOpenSuccesses,
        );
    }

    public function withLastFailure(int $lastFailure): self
    {
        return new self(
            state: $this->state,
            failures: $this->failures,
            lastFailure: max(0, $lastFailure),
            halfOpenRequests: $this->halfOpenRequests,
            halfOpenSuccesses: $this->halfOpenSuccesses,
        );
    }

    public function withHalfOpenRequests(int $halfOpenRequests): self
    {
        return new self(
            state: $this->state,
            failures: $this->failures,
            lastFailure: $this->lastFailure,
            halfOpenRequests: max(0, $halfOpenRequests),
            halfOpenSuccesses: $this->halfOpenSuccesses,
        );
    }

    public function withHalfOpenSuccesses(int $halfOpenSuccesses): self
    {
        return new self(
            state: $this->state,
            failures: $this->failures,
            lastFailure: $this->lastFailure,
            halfOpenRequests: $this->halfOpenRequests,
            halfOpenSuccesses: max(0, $halfOpenSuccesses),
        );
    }

    private static function isValidState(string $state): bool
    {
        return match ($state) {
            self::STATE_CLOSED, self::STATE_OPEN, self::STATE_HALF_OPEN => true,
            default => false,
        };
    }
}
