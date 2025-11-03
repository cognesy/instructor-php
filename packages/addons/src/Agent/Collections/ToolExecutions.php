<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Collections;

use Cognesy\Addons\Agent\Data\AgentExecution;
use Throwable;

final readonly class ToolExecutions
{
    /** @var AgentExecution[] */
    private array $toolExecutions;

    public function __construct(AgentExecution ...$toolExecutions) {
        $this->toolExecutions = $toolExecutions;
    }

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function fromArray(array $data): self {
        return new self(
            ...array_map(fn($executionData) => AgentExecution::fromArray($executionData), $data)
        );
    }

    // ACCESSORS ///////////////////////////////////////////////

    public function withAddedExecution(AgentExecution $toolExecution): self {
        $newExecutions = $this->toolExecutions;
        $newExecutions[] = $toolExecution;
        return new self(...$newExecutions);
    }

    public function hasExecutions() : bool {
        return count($this->toolExecutions) > 0;
    }

    /** @return AgentExecution[] */
    public function all(): array {
        return $this->toolExecutions;
    }

    public function hasErrors(): bool {
        return count($this->havingErrors()) > 0;
    }

    /** @return AgentExecution[] */
    public function havingErrors(): array {
        return array_filter($this->toolExecutions, fn(AgentExecution $toolExecution) => $toolExecution->hasError());
    }

    /** @return array<array-key, Throwable> */
    public function errors() : array {
        $errors = [];
        foreach($this->toolExecutions as $toolExecution) {
            if ($toolExecution->hasError()) {
                $error = $toolExecution->error();
                if ($error !== null) {
                    $errors[] = $error;
                }
            }
        }
        return $errors;
    }

    // TRANSFORMATIONS / CONVERSIONS ///////////////////////////

    public function toArray(): array {
        return array_map(
            fn(AgentExecution $toolExecution) => $toolExecution->toArray(),
            $this->toolExecutions
        );
    }
}