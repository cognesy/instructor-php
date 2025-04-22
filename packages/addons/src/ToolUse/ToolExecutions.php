<?php

namespace Cognesy\Addons\ToolUse;

use Throwable;

class ToolExecutions
{
    /** @var ToolExecution[] */
    private array $toolExecutions;

    /**
     * @param ToolExecution[] $toolExecutions
     */
    public function __construct(array $toolExecutions = []) {
        $this->toolExecutions = $toolExecutions;
    }

    public function add(ToolExecution $toolExecution): self {
        $this->toolExecutions[] = $toolExecution;
        return $this;
    }

    public function hasExecutions() : bool {
        return count($this->toolExecutions) > 0;
    }

    /**
     * @return ToolExecution[]
     */
    public function all(): array {
        return $this->toolExecutions;
    }

    public function hasErrors(): bool {
        return count($this->withErrors()) > 0;
    }

    /**
     * @return ToolExecution[]
     */
    public function withErrors(): array {
        return array_filter($this->toolExecutions, fn(ToolExecution $toolExecution) => $toolExecution->hasError());
    }

    /**
     * @return Throwable[]
     */
    public function errors() : array {
        $errors = [];
        foreach($this->toolExecutions as $toolExecution) {
            if ($toolExecution->hasError()) {
                $errors[] = $toolExecution->error();
            }
        }
        return $errors;
    }
}