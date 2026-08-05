<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, EvalLog> */
final readonly class EvalLogs implements Countable, IteratorAggregate
{
    /** @var list<EvalLog> */
    private array $logs;

    public function __construct(EvalLog ...$logs) {
        $this->logs = $logs;
    }

    public static function none(): self {
        return new self();
    }

    public function with(EvalLog $log): self {
        return new self(...[...$this->logs, $log]);
    }

    /** @return list<EvalLog> */
    public function all(): array {
        return $this->logs;
    }

    #[Override]
    public function count(): int {
        return count($this->logs);
    }

    #[Override]
    public function getIterator(): Traversable {
        yield from $this->logs;
    }
}
