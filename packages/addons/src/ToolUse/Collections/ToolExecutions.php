<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Collections;

use Cognesy\Addons\ToolUse\Data\ToolExecution;
use Throwable;

final readonly class ToolExecutions
{
    /** @var ToolExecution[] */
    private array $toolExecutions;

    public function __construct(ToolExecution ...$toolExecutions) {
        $this->toolExecutions = $toolExecutions;
    }

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function fromArray(array $data): self {
        return new self(
            ...array_map(fn($executionData) => ToolExecution::fromArray($executionData), $data)
        );
    }

    // ACCESSORS ///////////////////////////////////////////////

    public function withAddedExecution(ToolExecution $toolExecution): self {
        $newExecutions = $this->toolExecutions;
        $newExecutions[] = $toolExecution;
        return new self(...$newExecutions);
    }

    public function hasExecutions() : bool {
        return count($this->toolExecutions) > 0;
    }

    /** @return ToolExecution[] */
    public function all(): array {
        return $this->toolExecutions;
    }

    public function hasErrors(): bool {
        return count($this->havingErrors()) > 0;
    }

    /** @return ToolExecution[] */
    public function havingErrors(): array {
        return array_filter($this->toolExecutions, fn(ToolExecution $toolExecution) => $toolExecution->hasError());
    }

    /** @return Throwable[] */
    public function errors() : array {
        $errors = [];
        foreach($this->toolExecutions as $toolExecution) {
            if ($toolExecution->hasError()) {
                $errors[] = $toolExecution->error();
            }
        }
        return $errors;
    }

    // TRANSFORMATIONS / CONVERSIONS ///////////////////////////

    public function toArray(): array {
        return array_map(
            fn(ToolExecution $toolExecution) => $toolExecution->toArray(),
            $this->toolExecutions
        );
    }
}