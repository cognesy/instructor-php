<?php

namespace Cognesy\Instructor\Extras\Toolset\Traits;

use Cognesy\Instructor\Schema\Data\Schema\Schema;

trait HandlesSchemas
{
    public function toToolCallsJson(): array {
        $toolCalls = [];
        foreach ($this->tools as $tool) {
            $toolCalls[] = $tool->toToolCall();
        }
        return $toolCalls;
    }

    public function toJsonSchema(): array {
        return [];
    }

    public function toSchema(): Schema {
        return Schema::undefined();
    }
}