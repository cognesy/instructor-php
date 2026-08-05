<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Closure;
use Override;

final readonly class ConsoleEvalReporter implements CanReportAgentEvals
{
    /** @param Closure(string): void $write */
    public function __construct(private Closure $write, private bool $verbose = false) {}

    /** @param Closure(string): void $write */
    public static function fromWriter(Closure $write, bool $verbose = false): self {
        return new self($write, $verbose);
    }

    public function withVerbose(bool $verbose): self {
        return new self($this->write, $verbose);
    }

    #[Override]
    public function id(): string {
        return 'console';
    }

    #[Override]
    public function onRunStarted(int $caseCount): void {
        ($this->write)("Running {$caseCount} agent evals\n");
    }

    #[Override]
    public function onEvalCompleted(EvalResult $result): void {
        ($this->write)(sprintf("[%s] %s (%.1fms)\n", strtoupper($result->verdict()->value), $result->id(), $result->duration() * 1000));
        if ($result->error() !== null) {
            ($this->write)("  ERROR {$result->error()}\n");
        }
        if ($result->skipReason() !== null) {
            ($this->write)("  SKIP {$result->skipReason()}\n");
        }
        foreach ($result->assertions() as $assertion) {
            if ($assertion->passed() && !$this->verbose) {
                continue;
            }
            ($this->write)(sprintf("  %s %s score=%.2f threshold=%.2f%s\n", $assertion->passed() ? 'PASS' : 'FAIL', $assertion->label() ?? $assertion->name(), $assertion->score(), $assertion->threshold() ?? 1.0, $assertion->message() !== '' ? ' ' . $assertion->message() : ''));
        }
        if ($this->verbose) {
            foreach ($result->logs() as $log) {
                ($this->write)('  LOG ' . $log->message() . ' ' . json_encode($log->context(), JSON_THROW_ON_ERROR) . "\n");
            }
        }
    }

    #[Override]
    public function onRunCompleted(EvalRunResult $result): void {
        $counts = $result->toArray()['counts'];
        ($this->write)(sprintf("passed=%d failed=%d scored=%d skipped=%d\n", $counts['passed'], $counts['failed'], $counts['scored'], $counts['skipped']));
    }
}
