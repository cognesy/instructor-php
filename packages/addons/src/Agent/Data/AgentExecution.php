<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Data;

use Cognesy\Addons\Agent\Exceptions\ToolExecutionException;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use Cognesy\Utils\Result\Success;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;
use Throwable;

final readonly class AgentExecution
{
    private ToolCall $toolCall;
    private Result $result;
    private string $id;
    private DateTimeImmutable $startedAt;
    private DateTimeImmutable $endedAt;

    public function __construct(
        ToolCall $toolCall,
        Result $result,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $endedAt,
        ?string $id = null,
    ) {
        $this->toolCall = $toolCall;
        $this->result = $result;
        $this->startedAt = $startedAt;
        $this->endedAt = $endedAt;
        $this->id = $id ?? Uuid::uuid4();
    }

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function fromArray(array $data) : AgentExecution {
        return new AgentExecution(
            toolCall: self::hydrateToolCall($data),
            result: self::makeResult($data),
            startedAt: self::parseDate($data['startedAt'] ?? null),
            endedAt: self::parseDate($data['endedAt'] ?? null),
            id: $data['id'] ?? null,
        );
    }

    // ACCESSORS ///////////////////////////////////////////////

    public function toolCall() : ToolCall {
        return $this->toolCall;
    }

    public function id() : string {
        return $this->id;
    }

    public function startedAt() : DateTimeImmutable {
        return $this->startedAt;
    }

    public function endedAt() : DateTimeImmutable {
        return $this->endedAt;
    }

    public function name() : string {
        return $this->toolCall->name();
    }

    public function args() : array {
        return $this->toolCall->args();
    }

    public function result() : Result {
        return $this->result;
    }

    public function value() : mixed {
        return match (true) {
            $this->result instanceof Success => $this->result->unwrap(),
            default => null,
        };
    }

    public function error() : ?Throwable {
        return $this->result instanceof Failure
            ? $this->result->exception()
            : null;
    }

    public function hasError() : bool {
        return $this->result->isFailure();
    }

    // TRANSFORMATIONS / CONVERSIONS ////////////////////////////

    public function toArray() : array {
        $failure = $this->result instanceof Failure ? $this->result : null;

        return [
            'toolCall' => [
                'id' => $this->toolCall->id(),
                'name' => $this->toolCall->name(),
                'arguments' => $this->toolCall->args(),
            ],
            'tool' => $this->toolCall->name(),
            'args' => $this->toolCall->args(),
            'result' => $this->result->isSuccess() ? $this->value() : null,
            'error' => $failure?->errorMessage(),
            'startedAt' => $this->startedAt->format(DateTimeImmutable::ATOM),
            'endedAt' => $this->endedAt->format(DateTimeImmutable::ATOM),
        ];
    }

    // INTERNAL ////////////////////////////////////////////////

    private static function makeResult(array $data) : Result {
        if (array_key_exists('error', $data) && $data['error'] !== null && $data['error'] !== '') {
            return self::makeFailure($data['error']);
        }
        if (array_key_exists('result', $data)) {
            return Result::from($data['result']);
        }
        return Result::success(null);
    }

    private static function makeFailure(mixed $error) : Failure {
        if ($error instanceof Failure) {
            return $error;
        }

        return match (true) {
            $error instanceof Throwable => Result::failure($error),
            is_array($error) && isset($error['message']) && is_string($error['message'])
                => Result::failure(new ToolExecutionException($error['message'])),
            is_array($error) && isset($error['error']) && is_string($error['error'])
                => Result::failure(new ToolExecutionException($error['error'])),
            is_string($error) && $error !== ''
                => Result::failure(new ToolExecutionException($error)),
            default => Result::failure(new ToolExecutionException('Unknown error')),
        };
    }

    private static function hydrateToolCall(array $data) : ToolCall {
        $toolCallPayload = $data['toolCall'] ?? [];
        if (!is_array($toolCallPayload)) {
            $toolCallPayload = [];
        }

        if ($toolCallPayload === []) {
            $toolCallPayload = [
                'id' => $data['toolCallId'] ?? $data['tool_call_id'] ?? '',
                'name' => $data['tool'] ?? '',
                'arguments' => $data['arguments'] ?? $data['args'] ?? [],
            ];
        }

        if (isset($toolCallPayload['args']) && !isset($toolCallPayload['arguments'])) {
            $toolCallPayload['arguments'] = $toolCallPayload['args'];
        }

        $toolCall = ToolCall::fromArray($toolCallPayload);
        if ($toolCall === null) {
            throw new ToolExecutionException('Tool execution payload is missing tool call information.');
        }

        $id = $toolCallPayload['id'] ?? '';
        return $id !== '' ? $toolCall->withId((string) $id) : $toolCall;
    }

    private static function parseDate(mixed $value) : DateTimeImmutable {
        return match (true) {
            $value instanceof DateTimeImmutable => $value,
            is_string($value) && $value !== '' => new DateTimeImmutable($value),
            default => new DateTimeImmutable(),
        };
    }
}
