<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use OutOfBoundsException;

final class AssertionCollector
{
    /** @var list<AssertionResult> */
    private array $results = [];

    public function record(AssertionResult $result): AssertionHandle {
        $this->results[] = $result;
        return new AssertionHandle($this, array_key_last($this->results));
    }

    public function replace(int $index, AssertionResult $result): void {
        if (!isset($this->results[$index])) {
            throw new OutOfBoundsException("Unknown assertion index {$index}.");
        }
        $this->results[$index] = $result;
    }

    public function at(int $index): AssertionResult {
        return $this->results[$index] ?? throw new OutOfBoundsException("Unknown assertion index {$index}.");
    }

    public function results(): AssertionResults {
        return new AssertionResults(...$this->results);
    }
}
