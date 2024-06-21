<?php

namespace Cognesy\Instructor\Extras\Agent\Traits\Tool;

use Cognesy\Instructor\Extras\FunctionCall\FunctionCall;
use Cognesy\Instructor\Schema\Data\Schema\Schema;

trait HandlesSchemas
{
    private FunctionCall $call;

    public function getCall(): FunctionCall {
        return $this->call;
    }

    protected function getResponseModel(): string|array|object {
        return $this->call->toSchema();
    }

    public function toToolCall() : array {
        return $this->call->toToolCall();
    }

    public function toSchema() : Schema {
        return $this->call->toSchema();
    }

    public function toJsonSchema() : array {
        return $this->call->toJsonSchema();
    }
}