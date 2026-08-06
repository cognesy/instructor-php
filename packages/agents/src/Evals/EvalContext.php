<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Closure;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Events\SubagentCompleted;

final class EvalContext
{
    private readonly CanUseAgentEvalSession $session;
    private readonly AssertionCollector $collector;
    private readonly EvalLogCollector $logCollector;

    public function __construct(
        private readonly CanRunAgentEvalTarget $target,
        ?CanUseAgentEvalSession $session = null,
        ?AssertionCollector $collector = null,
        ?EvalLogCollector $logCollector = null,
        private readonly ?CanJudgeAgentEval $judge = null,
    ) {
        $this->session = $session ?? $target->open();
        $this->collector = $collector ?? new AssertionCollector();
        $this->logCollector = $logCollector ?? new EvalLogCollector();
    }

    public function send(string $message): EvalTurn {
        return $this->session->send($message);
    }

    public function run(): AgentRun {
        return $this->session->run();
    }

    public function assertions(): AssertionResults {
        return $this->collector->results();
    }

    public function logs(): EvalLogs {
        return $this->logCollector->logs();
    }

    public function newSession(): self {
        return new self($this->target, collector: $this->collector, logCollector: $this->logCollector, judge: $this->judge);
    }

    /** @param array<string, mixed> $context */
    public function log(string $message, array $context = []): void {
        $this->logCollector->record($message, $context);
    }

    public function check(string $name, bool $passed, string $message = ''): AssertionHandle {
        return $this->collector->record(new AssertionResult($name, $passed ? 1.0 : 0.0, message: $message));
    }

    public function require(string $name, bool $passed, string $message = ''): void {
        $this->check($name, $passed, $message);
        if (!$passed) {
            throw new EvalRequirementFailed($message !== '' ? $message : $name);
        }
    }

    public function skip(string $reason): never {
        throw new EvalSkipped($reason);
    }

    public function expect(mixed $value): ValueExpectation {
        return new ValueExpectation($value, $this->collector);
    }

    public function judge(): AgentJudgeAssertions {
        return new AgentJudgeAssertions($this->judge, $this->run(), $this->collector);
    }

    public function succeeded(): AssertionHandle {
        return $this->check('succeeded', $this->run()->succeeded());
    }

    public function stopped(): AssertionHandle {
        return $this->check('stopped', $this->run()->status() === ExecutionStatus::Stopped);
    }

    public function messageIncludes(string $text): AssertionHandle {
        return $this->check('messageIncludes', str_contains($this->run()->reply(), $text));
    }

    public function outputEquals(mixed $expected): AssertionHandle {
        return $this->check('outputEquals', EvalMatcher::matches($expected, $this->run()->reply()));
    }

    public function outputMatches(string $pattern): AssertionHandle {
        return $this->check('outputMatches', EvalMatch::regex($pattern)->matches($this->run()->reply()));
    }

    public function calledTool(
        string $name,
        mixed $arguments = null,
        mixed $result = null,
        ?bool $isError = null,
        int|EvalCount|null $count = null,
    ): AssertionHandle {
        $matches = [];
        foreach ($this->run()->tools() as $execution) {
            $matches[] = $execution->name() === $name
                && ($arguments === null || EvalMatcher::matches($arguments, $execution->args()))
                && ($result === null || EvalMatcher::matches($result, $execution->value()))
                && ($isError === null || $execution->hasError() === $isError);
        }
        $actual = count(array_filter($matches));
        $passed = match (true) {
            is_int($count) => $actual === $count,
            $count instanceof EvalCount => $count->matches($actual),
            default => $actual > 0,
        };
        return $this->check("calledTool:{$name}", $passed, "matched {$actual} tool calls");
    }

    public function notCalledTool(string $name, mixed $arguments = null): AssertionHandle {
        foreach ($this->run()->tools() as $execution) {
            $matches = $execution->name() === $name
                && ($arguments === null || EvalMatcher::matches($arguments, $execution->args()));
            if ($matches) {
                return $this->check("notCalledTool:{$name}", false);
            }
        }
        return $this->check("notCalledTool:{$name}", true);
    }

    public function toolOrder(string ...$names): AssertionHandle {
        $actual = array_map(static fn ($execution): string => $execution->name(), $this->run()->tools()->all());
        $position = 0;
        foreach ($actual as $name) {
            if (($names[$position] ?? null) === $name) {
                $position++;
            }
        }
        return $this->check('toolOrder', $position === count($names));
    }

    public function usedNoTools(): AssertionHandle {
        return $this->check('usedNoTools', $this->run()->tools()->count() === 0);
    }

    public function maxToolCalls(int $maximum): AssertionHandle {
        return $this->check('maxToolCalls', $this->run()->tools()->count() <= $maximum);
    }

    public function stepCount(int $expected): AssertionHandle {
        $actual = $this->run()->stepCount();
        return $this->check('stepCount', $actual === $expected, "expected {$expected} steps, got {$actual}");
    }

    public function maxSteps(int $maximum): AssertionHandle {
        $actual = $this->run()->stepCount();
        return $this->check('maxSteps', $actual <= $maximum, "expected at most {$maximum} steps, got {$actual}");
    }

    public function totalTokensAtMost(int $maximum): AssertionHandle {
        $actual = $this->run()->usage()->total();
        return $this->check('totalTokensAtMost', $actual <= $maximum, "used {$actual} tokens, limit {$maximum}");
    }

    public function noFailedActions(): AssertionHandle {
        foreach ($this->run()->tools() as $execution) {
            if ($execution->hasError()) {
                return $this->check('noFailedActions', false);
            }
        }
        return $this->check('noFailedActions', $this->run()->errors() === '');
    }

    public function calledSubagent(string $name, int|EvalCount|null $count = null): AssertionHandle {
        $actual = count(array_filter($this->run()->events()->all(), static fn (object $event): bool
            => $event instanceof SubagentCompleted && $event->subagentName === $name));
        $passed = match (true) {
            is_int($count) => $actual === $count,
            $count instanceof EvalCount => $count->matches($actual),
            default => $actual > 0,
        };
        return $this->check("calledSubagent:{$name}", $passed);
    }

    /** @param class-string $class @param Closure(object): bool|null $predicate */
    public function event(string $class, ?Closure $predicate = null, int|EvalCount|null $count = null): AssertionHandle {
        $matches = array_filter($this->run()->events()->all(), static fn (object $event): bool
            => $event instanceof $class && ($predicate === null || $predicate($event)));
        $actual = count($matches);
        $passed = match (true) {
            is_int($count) => $actual === $count,
            $count instanceof EvalCount => $count->matches($actual),
            default => $actual > 0,
        };
        return $this->check("event:{$class}", $passed);
    }

    /** @param class-string $class */
    public function notEvent(string $class): AssertionHandle {
        foreach ($this->run()->events() as $event) {
            if ($event instanceof $class) {
                return $this->check("notEvent:{$class}", false);
            }
        }
        return $this->check("notEvent:{$class}", true);
    }

    /** @param class-string ...$classes */
    public function eventOrder(string ...$classes): AssertionHandle {
        $position = 0;
        foreach ($this->run()->events() as $event) {
            $class = $classes[$position] ?? null;
            if ($class !== null && $event instanceof $class) {
                $position++;
            }
        }
        return $this->check('eventOrder', $position === count($classes));
    }

    /** @param Closure(EvalEvents): bool $predicate */
    public function eventsSatisfy(Closure $predicate): AssertionHandle {
        return $this->check('eventsSatisfy', (bool) $predicate($this->run()->events()));
    }
}
