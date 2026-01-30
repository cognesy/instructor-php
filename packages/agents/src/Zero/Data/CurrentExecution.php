<?php declare(strict_types=1);

namespace Cognesy\Agents\Zero\Data;

use Cognesy\Agents\Core\Collections\ErrorList;
use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Zero\Stop\ContinuationOutcome;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

/**
 * Transient execution context for the currently running step.
 *
 * Includes:
 * - Evaluation accumulation for hook-based flow control
 * - Event-specific context (tool calls, inference) for hook access
 * - Step-level transient fields (populated before AfterStep hooks)
 *
 * All fields except id, stepNumber, startedAt are transient (not serialized).
 */
final readonly class CurrentExecution
{
    public string $id;

    /**
     * @param list<ContinuationEvaluation> $evaluations Accumulated evaluations from hooks
     */
    public function __construct(
        public int $stepNumber,
        public DateTimeImmutable $startedAt = new DateTimeImmutable(),
        string $id = '',
        // Error context
        public ?\Throwable $exception = null,
        public ?ContinuationOutcome $continuationOutcome = null,
        public ?ErrorList $errors = null,
    ) {
        $this->id = $id !== '' ? $id : Uuid::uuid4();
    }

    // CORE MUTATOR ////////////////////////////////////////////

    // ACCESSORS ///////////////////////////////////////////////

    public function id(): string
    {
        return $this->id;
    }

    public function stepNumber(): int
    {
        return $this->stepNumber;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function exception(): ?\Throwable
    {
        return $this->exception;
    }

    public function continuationOutcome(): ?ContinuationOutcome
    {
        return $this->continuationOutcome;
    }

    public function errors(): ?ErrorList
    {
        return $this->errors;
    }

    // SERIALIZATION ///////////////////////////////////////////

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'stepNumber' => $this->stepNumber,
            'startedAt' => $this->startedAt->format(DateTimeImmutable::ATOM),
            // Transient fields are NOT serialized
        ];
    }

    public static function fromArray(array $data): self
    {
        $stepNumberValue = $data['stepNumber'] ?? null;
        $stepNumber = is_int($stepNumberValue) ? $stepNumberValue : (int) $stepNumberValue;

        $startedAtValue = $data['startedAt'] ?? null;
        $startedAt = match (true) {
            $startedAtValue instanceof DateTimeImmutable => $startedAtValue,
            is_string($startedAtValue) && $startedAtValue !== '' => new DateTimeImmutable($startedAtValue),
            default => new DateTimeImmutable(),
        };

        $idValue = $data['id'] ?? '';
        $id = is_string($idValue) ? $idValue : '';

        return new self(
            stepNumber: $stepNumber,
            startedAt: $startedAt,
            id: $id,
        );
    }
}
