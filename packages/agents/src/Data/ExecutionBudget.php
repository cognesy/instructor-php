<?php declare(strict_types=1);

namespace Cognesy\Agents\Data;

use DateTimeImmutable;

/**
 * Represents resource limits for a single agent execution.
 *
 * All limits are optional (null = unlimited).
 * Declared in AgentDefinition and applied via UseGuards capability.
 */
final readonly class ExecutionBudget
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

    public function isEmpty(): bool {
        return $this->maxSteps === null
            && $this->maxTokens === null
            && $this->maxSeconds === null
            && $this->maxCost === null
            && $this->deadline === null;
    }

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
}
