<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data;

use Cognesy\Polyglot\Inference\Data\ToolCall;
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
}
