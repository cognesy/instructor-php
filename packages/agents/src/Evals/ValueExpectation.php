<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Closure;

final class ValueExpectation
{
    private ?AssertionHandle $last = null;

    public function __construct(
        private readonly mixed $value,
        private readonly AssertionCollector $collector,
    ) {}

    public function includes(mixed $expected): self {
        $passed = match (true) {
            is_string($this->value) => str_contains($this->value, (string) $expected),
            is_array($this->value) => in_array($expected, $this->value, true),
            default => false,
        };
        return $this->record('includes', $passed ? 1.0 : 0.0);
    }

    public function equals(mixed $expected): self {
        return $this->record('equals', EvalMatcher::matches($expected, $this->value) ? 1.0 : 0.0);
    }

    public function matches(string|EvalMatch $pattern): self {
        $matcher = is_string($pattern) ? EvalMatch::regex($pattern) : $pattern;
        return $this->record('matches', $matcher->matches($this->value) ? 1.0 : 0.0);
    }

    public function similarity(string $expected): self {
        $actual = (string) $this->value;
        $maximum = max(strlen($actual), strlen($expected));
        $score = $maximum === 0 ? 1.0 : 1.0 - (levenshtein($actual, $expected) / $maximum);
        return $this->record('similarity', max(0.0, $score), AssertionSeverity::Soft);
    }

    /** @param Closure(mixed): bool $predicate */
    public function satisfies(Closure $predicate): self {
        return $this->record('satisfies', (bool) $predicate($this->value) ? 1.0 : 0.0);
    }

    public function gate(): self {
        $this->last?->gate();
        return $this;
    }

    public function soft(): self {
        $this->last?->soft();
        return $this;
    }

    public function atLeast(float $threshold): self {
        $this->last?->atLeast($threshold);
        return $this;
    }

    public function label(string $label): self {
        $this->last?->label($label);
        return $this;
    }

    private function record(string $name, float $score, AssertionSeverity $severity = AssertionSeverity::Gate): self {
        $this->last = $this->collector->record(new AssertionResult($name, $score, $severity));
        return $this;
    }
}
