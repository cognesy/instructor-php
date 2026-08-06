<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Closure;
use OutOfBoundsException;

final class AssertionCollector
{
    /** @var list<AssertionResult> */
    private array $results = [];

    /**
     * Deferred resolvers keyed by result index, e.g. a `JudgeExpectation` whose
     * judge has not run yet. `at()` and `results()` are the only two read paths,
     * and both resolve a pending slot - at most once - before returning it.
     *
     * @var array<int, Closure(): AssertionResult>
     */
    private array $pending = [];

    public function record(AssertionResult $result): AssertionHandle {
        $this->results[] = $result;
        return new AssertionHandle($this, array_key_last($this->results));
    }

    /**
     * Reserves a slot holding `$placeholder` and defers producing the real result
     * until first read. `$resolve` runs at most once: reads are memoized by
     * discarding the resolver as soon as it has run.
     *
     * @param Closure(): AssertionResult $resolve
     */
    public function recordLazy(AssertionResult $placeholder, Closure $resolve): void {
        $this->results[] = $placeholder;
        $this->pending[array_key_last($this->results)] = $resolve;
    }

    public function replace(int $index, AssertionResult $result): void {
        if (!isset($this->results[$index])) {
            throw new OutOfBoundsException("Unknown assertion index {$index}.");
        }
        $this->results[$index] = $result;
        unset($this->pending[$index]);
    }

    public function at(int $index): AssertionResult {
        $this->resolve($index);
        return $this->results[$index] ?? throw new OutOfBoundsException("Unknown assertion index {$index}.");
    }

    public function results(): AssertionResults {
        foreach (array_keys($this->pending) as $index) {
            $this->resolve($index);
        }
        return new AssertionResults(...$this->results);
    }

    private function resolve(int $index): void {
        $resolver = $this->pending[$index] ?? null;
        if ($resolver === null) {
            return;
        }
        unset($this->pending[$index]);
        $this->results[$index] = $resolver();
    }
}
