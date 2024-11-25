<?php

namespace Cognesy\Instructor\Extras\ToolUse\Traits\ToolUseStep;

use Cognesy\Instructor\Extras\ToolUse\ToolExecutions;
use Throwable;

trait HandlesErrors
{
    public function hasErrors() : bool {
        return match($this->toolExecutions) {
            null => false,
            default => $this->toolExecutions->hasErrors(),
        };
    }

    /**
     * @return Throwable[]
     */
    public function errors() : array {
        return $this->toolExecutions?->errors() ?? [];
    }

    public function errorsAsString() : string {
        return implode("\n", array_map(
            callback: fn(Throwable $e) => $e->getMessage(),
            array: $this->errors(),
        ));
    }

    public function errorExecutions() : ToolExecutions {
        return match($this->toolExecutions) {
            null => new ToolExecutions(),
            default => new ToolExecutions($this->toolExecutions->withErrors()),
        };
    }
}
