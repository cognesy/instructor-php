<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Data;

use DateTimeImmutable;

/**
 * Represents resource limits for agent execution.
 *
 * All limits are optional (null = unlimited).
 * Budget propagates through delegation chains - each level inherits
 * the remaining budget from its parent, ensuring global limits are respected.
 */
final readonly class Budget
{
    public function __construct(
        public ?int $maxSteps = null,
        public ?int $maxTokens = null,
        public ?float $maxSeconds = null,
        public ?float $maxCost = null,
        public ?DateTimeImmutable $deadline = null,
    ) {}

    public static function unlimited(): self {
        return new self();
    }

    public static function fromDefinition(
        ?int $maxSteps,
        ?int $maxTokens,
        ?int $timeoutSec,
    ): self {
        return new self(
            maxSteps: $maxSteps,
            maxTokens: $maxTokens,
            maxSeconds: $timeoutSec !== null ? (float) $timeoutSec : null,
        );
    }

    public function isEmpty(): bool {
        return $this->maxSteps === null
            && $this->maxTokens === null
            && $this->maxSeconds === null
            && $this->maxCost === null
            && $this->deadline === null;
    }

    /**
     * Compute remaining budget after consuming resources.
     */
    public function remaining(
        int $stepsUsed = 0,
        int $tokensUsed = 0,
        float $secondsElapsed = 0.0,
        float $costIncurred = 0.0,
    ): self {
        return new self(
            maxSteps: $this->remainingInt($this->maxSteps, $stepsUsed),
            maxTokens: $this->remainingInt($this->maxTokens, $tokensUsed),
            maxSeconds: $this->remainingFloat($this->maxSeconds, $secondsElapsed),
            maxCost: $this->remainingFloat($this->maxCost, $costIncurred),
            deadline: $this->deadline, // deadline is absolute, doesn't change
        );
    }

    /**
     * Compute remaining budget from agent execution state.
     */
    public function remainingFrom(ExecutionState $execution): self {
        return $this->remaining(
            stepsUsed: $execution->stepCount(),
            tokensUsed: $execution->usage()->total(),
            secondsElapsed: $execution->totalDuration(),
            // cost would need to be tracked separately if needed
        );
    }

    /**
     * Return a budget capped by another budget (takes minimum of each limit).
     */
    public function cappedBy(self $other): self {
        return new self(
            maxSteps: $this->minNullable($this->maxSteps, $other->maxSteps),
            maxTokens: $this->minNullable($this->maxTokens, $other->maxTokens),
            maxSeconds: $this->minNullableFloat($this->maxSeconds, $other->maxSeconds),
            maxCost: $this->minNullableFloat($this->maxCost, $other->maxCost),
            deadline: $this->earlierDeadline($this->deadline, $other->deadline),
        );
    }

    /**
     * Check if any limit is exhausted (zero or negative remaining).
     */
    public function isExhausted(): bool {
        return match (true) {
            $this->maxSteps !== null && $this->maxSteps <= 0 => true,
            $this->maxTokens !== null && $this->maxTokens <= 0 => true,
            $this->maxSeconds !== null && $this->maxSeconds <= 0.0 => true,
            $this->maxCost !== null && $this->maxCost <= 0.0 => true,
            $this->deadline !== null && $this->deadline <= new DateTimeImmutable() => true,
            default => false,
        };
    }

    // SERIALIZATION ////////////////////////////////////////////////////

    public function toArray(): array {
        return [
            'maxSteps' => $this->maxSteps,
            'maxTokens' => $this->maxTokens,
            'maxSeconds' => $this->maxSeconds,
            'maxCost' => $this->maxCost,
            'deadline' => $this->deadline?->format(DateTimeImmutable::ATOM),
        ];
    }

    public static function fromArray(array $data): self {
        $deadline = match (true) {
            isset($data['deadline']) && is_string($data['deadline']) => new DateTimeImmutable($data['deadline']),
            default => null,
        };

        return new self(
            maxSteps: $data['maxSteps'] ?? null,
            maxTokens: $data['maxTokens'] ?? null,
            maxSeconds: $data['maxSeconds'] ?? null,
            maxCost: $data['maxCost'] ?? null,
            deadline: $deadline,
        );
    }

    // PRIVATE HELPERS //////////////////////////////////////////////////

    private function remainingInt(?int $limit, int $used): ?int {
        return match ($limit) {
            null => null,
            default => max(0, $limit - $used),
        };
    }

    private function remainingFloat(?float $limit, float $used): ?float {
        return match ($limit) {
            null => null,
            default => max(0.0, $limit - $used),
        };
    }

    private function minNullable(?int $a, ?int $b): ?int {
        return match (true) {
            $a === null && $b === null => null,
            $a === null => $b,
            $b === null => $a,
            default => min($a, $b),
        };
    }

    private function minNullableFloat(?float $a, ?float $b): ?float {
        return match (true) {
            $a === null && $b === null => null,
            $a === null => $b,
            $b === null => $a,
            default => min($a, $b),
        };
    }

    private function earlierDeadline(?DateTimeImmutable $a, ?DateTimeImmutable $b): ?DateTimeImmutable {
        return match (true) {
            $a === null && $b === null => null,
            $a === null => $b,
            $b === null => $a,
            $a <= $b => $a,
            default => $b,
        };
    }
}
