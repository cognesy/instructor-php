<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data;

use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;
use Throwable;

class ToolExecution
{
    private ToolCall $toolCall;
    private Result $result;
    private DateTimeImmutable $startedAt;
    private DateTimeImmutable $endedAt;

    public function __construct(
        ToolCall $toolCall,
        Result $result,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $endedAt,
    ) {
        $this->toolCall = $toolCall;
        $this->result = $result;
        $this->startedAt = $startedAt;
        $this->endedAt = $endedAt;
    }

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function fromArray(array $data) : ToolExecution {
        return new ToolExecution(
            toolCall: ToolCall::fromArray($data['toolCall']),
            result: self::makeResult($data['result']),
            startedAt: new DateTimeImmutable($data['startedAt']),
            endedAt: new DateTimeImmutable($data['endedAt']),
        );
    }

    // ACCESSORS ///////////////////////////////////////////////

    public function toolCall() : ToolCall {
        return $this->toolCall;
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
        return $this->result->unwrap();
    }

    public function error() : ?Throwable {
        return $this->result->error();
    }

    public function hasError() : bool {
        return $this->result->isFailure();
    }

    // TRANSFORMATIONS / CONVERSIONS ////////////////////////////

    public function toArray() : array {
        return [
            'tool' => $this->toolCall->name(),
            'args' => $this->toolCall->args(),
            'result' => $this->result->isSuccess() ? $this->result->unwrap() : null,
            'error' => $this->result->isFailure() ? $this->result->error()->getMessage() : null,
            'startedAt' => $this->startedAt->format(DateTimeImmutable::ATOM),
            'endedAt' => $this->endedAt->format(DateTimeImmutable::ATOM),
        ];
    }

    // INTERNAL ////////////////////////////////////////////////

    private static function makeResult(array $data) : Result {
        return isset($data['result'])
            ? Result::success($data['result'])
            : self::makeFailure($data['error'] ?? []);
    }

    private static function makeFailure(array $data) : Failure {
        return isset($data['error'])
            ? Result::failure(new \Exception($data['error']))
            : Result::failure(new \Exception('Unknown error'));
    }
}
