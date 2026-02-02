<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Data;

use Cognesy\Agents\Exceptions\ToolCallBlockedException;
use Cognesy\Agents\Exceptions\ToolExecutionException;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use Cognesy\Utils\Result\Success;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

final readonly class ToolExecution
{
    private string $id;
    private DateTimeImmutable $startedAt;
    private DateTimeImmutable $completedAt;
    private ToolCall $toolCall;
    private Result $result;

    public function __construct(
        ToolCall $toolCall,
        Result $result,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $completedAt,
        ?string $id = null,
    ) {
        $this->toolCall = $toolCall;
        $this->result = $result;
        $this->startedAt = $startedAt;
        $this->completedAt = $completedAt;
        $this->id = $id ?? Uuid::uuid4();
    }

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function blocked(ToolCall $toolCall, string $message) : ToolExecution {
        $now = new DateTimeImmutable();
        return new ToolExecution(
            toolCall: $toolCall,
            result: Failure::from(new ToolExecutionException(
                message: $message,
                toolCall: $toolCall,
            )),
            startedAt: $now,
            completedAt: $now,
        );
    }

    // ACCESSORS ///////////////////////////////////////////////
    public function toolCall(): ToolCall {
        return $this->toolCall;
    }

    public function id(): string {
        return $this->id;
    }

    public function startedAt(): DateTimeImmutable {
        return $this->startedAt;
    }

    public function completedAt(): DateTimeImmutable {
        return $this->completedAt;
    }

    public function name(): string {
        return $this->toolCall->name();
    }

    public function args(): array {
        return $this->toolCall->args();
    }

    public function result(): Result {
        return $this->result;
    }

    public function value(): mixed {
        return match (true) {
            $this->result instanceof Success => $this->result->unwrap(),
            default => null,
        };
    }

    public function error(): ?Throwable {
        return match (true) {
            $this->result instanceof Failure => $this->result->exception(),
            default => null,
        };
    }

    public function errorMessage(): string {
        return match (true) {
            $this->result instanceof Failure => $this->result->errorMessage(),
            default => '',
        };
    }

    public function hasError(): bool {
        return $this->result->isFailure();
    }

    public function errorAsString(): ?string {
        return match (true) {
            $this->result instanceof Failure => $this->result->errorMessage(),
            default => null,
        };
    }

    public function wasBlocked(): bool {
        return $this->error() instanceof ToolCallBlockedException;
    }

    // SERIALIZATION /////////////////////////////////////////

    public function toArray(): array {
        $failure = $this->result->isFailure() ? $this->result : null;

        return [
            'id' => $this->id,
            'tool_call' => [
                'id' => $this->toolCall->id(),
                'name' => $this->toolCall->name(),
                'arguments' => $this->toolCall->args(),
            ],
            'result' => $this->result->isSuccess() ? json_encode($this->value()) : null,
            'error' => $failure?->errorMessage(),
            'startedAt' => $this->startedAt->format(DateTimeInterface::ATOM),
            'completedAt' => $this->completedAt->format(DateTimeInterface::ATOM),
        ];
    }

    public static function fromArray(array $data): ToolExecution {
        return new ToolExecution(
            toolCall: ToolCall::fromArray($data['tool_call'] ?? []),
            result: self::makeResult($data),
            startedAt: self::parseDate($data['startedAt'] ?? null),
            completedAt: self::parseDate($data['completedAt'] ?? $data['endedAt'] ?? null),
            id: $data['id'] ?? null,
        );
    }

    // INTERNAL ////////////////////////////////////////////////

    private static function makeResult(array $data): Result {
        return match(true) {
            self::hasNonEmptyErrorKey($data) => self::makeFailure(
                error: $data['error'],
                toolCall: ToolCall::fromArray($data['tool_call'] ?? [])
            ),
            array_key_exists('result', $data) => Result::from($data['result']),
            default => Result::success(null),
        };
    }

    private static function hasNonEmptyErrorKey(array $data) : bool {
        return array_key_exists('error', $data)
            && $data['error'] !== null
            && $data['error'] !== '';
    }

    private static function makeFailure(mixed $error, ToolCall $toolCall): Failure {
        return match (true) {
            $error instanceof Failure => $error,
            $error instanceof Throwable => Result::failure($error),
            default => Result::failure(new ToolExecutionException('Unknown error', $toolCall)),
        };
    }

    private static function parseDate(mixed $value): DateTimeImmutable {
        return match (true) {
            $value instanceof DateTimeImmutable => $value,
            is_string($value) && $value !== '' => new DateTimeImmutable($value),
            default => new DateTimeImmutable(),
        };
    }
}
