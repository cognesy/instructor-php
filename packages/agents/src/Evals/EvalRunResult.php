<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, EvalResult> */
final readonly class EvalRunResult implements Countable, IteratorAggregate
{
    /** @var list<EvalResult> */
    private array $results;

    /** @var list<string> */
    private array $reporterErrors;

    /** @param list<string> $reporterErrors */
    public function __construct(array $reporterErrors, private bool $strict, EvalResult ...$results) {
        $this->results = $results;
        $this->reporterErrors = $reporterErrors;
    }

    /** @return list<EvalResult> */
    public function all(): array {
        return $this->results;
    }

    /** @return list<string> */
    public function reporterErrors(): array {
        return $this->reporterErrors;
    }

    public function strict(): bool {
        return $this->strict;
    }

    #[Override]
    public function count(): int {
        return count($this->results);
    }

    #[Override]
    public function getIterator(): Traversable {
        yield from $this->results;
    }

    public function exitCode(?bool $strict = null): EvalExitCode {
        $strict ??= $this->strict;
        foreach ($this->results as $result) {
            if ($result->verdict() === EvalVerdict::Failed || ($strict && $result->verdict() === EvalVerdict::Scored)) {
                return EvalExitCode::EvalFailure;
            }
        }
        return $this->reporterErrors !== [] ? EvalExitCode::EvalFailure : EvalExitCode::Success;
    }

    /**
     * Run-level target/judge provenance, aggregated across every result: the
     * first non-null target/judge LLM configuration found (a run's target and
     * judge configuration is expected to be stable across its evals), and
     * whether a `JudgeGuardsNotConfigured` warning was observed on ANY judged
     * result in the run. See `EvalResult::provenance()` for what each field
     * means and why `judge.temperature` is always null.
     *
     * @return array{target: array<string, mixed>|null, judge: array{class: string|null, llm: array<string, mixed>|null, temperature: null, guardsWarningObserved: bool}|null}
     */
    public function provenance(): array {
        $target = null;
        $sawJudge = false;
        $judgeClass = null;
        $judgeLlm = null;
        $guardsWarningObserved = false;
        foreach ($this->results as $result) {
            $resultProvenance = $result->provenance();
            $target ??= $resultProvenance['target'];
            $judge = $resultProvenance['judge'];
            if ($judge === null) {
                continue;
            }
            $sawJudge = true;
            $judgeClass ??= $judge['class'];
            $judgeLlm ??= $judge['llm'];
            $guardsWarningObserved = $guardsWarningObserved || $judge['guardsWarningObserved'];
        }
        return [
            'target' => $target,
            'judge' => !$sawJudge ? null : [
                'class' => $judgeClass,
                'llm' => $judgeLlm,
                'temperature' => null,
                'guardsWarningObserved' => $guardsWarningObserved,
            ],
        ];
    }

    /**
     * Target and judge token totals summed across every result in the run.
     *
     * @return array{target: int, judge: int, total: int}
     */
    public function tokens(): array {
        $target = 0;
        $judge = 0;
        foreach ($this->results as $result) {
            $resultTokens = $result->tokens();
            $target += $resultTokens['target'];
            $judge += $resultTokens['judge'];
        }
        return ['target' => $target, 'judge' => $judge, 'total' => $target + $judge];
    }

    /**
     * @param array<string, mixed>|null $envelope Run-environment facts the
     *        reporter alone can supply (package version, git sha, start time,
     *        repeat count) - merged into the `provenance` block verbatim.
     *        Omitted (null) leaves `provenance` at just `{target, judge}`.
     * @return array<string, mixed>
     */
    public function toArray(?array $envelope = null): array {
        $counts = ['passed' => 0, 'failed' => 0, 'scored' => 0, 'skipped' => 0];
        foreach ($this->results as $result) {
            $counts[$result->verdict()->value]++;
        }
        return [
            'strict' => $this->strict,
            'counts' => $counts,
            'reporterErrors' => $this->reporterErrors,
            'results' => array_map(static fn (EvalResult $result): array => $result->toArray(), $this->results),
            'provenance' => [...$this->provenance(), ...($envelope ?? [])],
            'tokens' => $this->tokens(),
        ];
    }
}
