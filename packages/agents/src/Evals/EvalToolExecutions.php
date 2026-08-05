<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Cognesy\Agents\Data\ToolExecution;
use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, ToolExecution> */
final readonly class EvalToolExecutions implements Countable, IteratorAggregate
{
    /** @var list<ToolExecution> */
    private array $executions;

    public function __construct(ToolExecution ...$executions) {
        $this->executions = $executions;
    }

    public static function none(): self {
        return new self();
    }

    public function with(ToolExecution ...$executions): self {
        return new self(...[...$this->executions, ...$executions]);
    }

    /** @return list<ToolExecution> */
    public function all(): array {
        return $this->executions;
    }

    #[Override]
    public function count(): int {
        return count($this->executions);
    }

    #[Override]
    public function getIterator(): Traversable {
        yield from $this->executions;
    }
}
