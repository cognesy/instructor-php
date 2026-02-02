<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Collections;

use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;

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

    public static function none() : self {
        return new self();
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

    public function toolCalls(): ToolCalls {
        $calls = array_map(
            static fn(ToolExecution $execution): ToolCall => $execution->toolCall(),
            $this->toolExecutions,
        );

        return new ToolCalls(...$calls);
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

    public function errors() : ErrorList {
        $errors = ErrorList::empty();
        foreach ($this->toolExecutions as $toolExecution) {
            if (!$toolExecution->hasError()) {
                continue;
            }
            $error = $toolExecution->error();
            if ($error === null) {
                continue;
            }
            $errors = $errors->withAppendedExceptions($error);
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
