<?php

namespace Cognesy\Instructor\Extras\Tools\Traits;

use Cognesy\Instructor\Extras\Call\Call;
use Cognesy\Instructor\Schema\Data\Schema\Schema;

trait HandlesSchemas
{
    private Call $call;

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