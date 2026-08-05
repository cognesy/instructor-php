<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, AssertionResult> */
final readonly class AssertionResults implements Countable, IteratorAggregate
{
    /** @var list<AssertionResult> */
    private array $results;

    public function __construct(AssertionResult ...$results) {
        $this->results = $results;
    }

    public static function none(): self {
        return new self();
    }

    public function with(AssertionResult $result): self {
        return new self(...[...$this->results, $result]);
    }

    /** @return list<AssertionResult> */
    public function all(): array {
        return $this->results;
    }

    public function hasFailedGate(): bool {
        foreach ($this->results as $result) {
            if ($result->severity() === AssertionSeverity::Gate && !$result->passed()) {
                return true;
            }
        }
        return false;
    }

    public function hasFailedSoft(): bool {
        foreach ($this->results as $result) {
            if ($result->severity() === AssertionSeverity::Soft && !$result->passed()) {
                return true;
            }
        }
        return false;
    }

    #[Override]
    public function count(): int {
        return count($this->results);
    }

    #[Override]
    public function getIterator(): Traversable {
        yield from $this->results;
    }
}
