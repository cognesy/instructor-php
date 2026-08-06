<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Cognesy\Agents\Evals\Events\JudgeGuardsNotConfigured;

final readonly class EvalResult
{
    public function __construct(
        private string $id,
        private string $description,
        private EvalVerdict $verdict,
        private AssertionResults $assertions,
        private AgentRun $run,
        private float $duration,
        private ?string $error = null,
        private ?string $skipReason = null,
        private EvalLogs $logs = new EvalLogs(),
        private ?EvalRepetition $repetition = null,
    ) {}

    public function id(): string {
        return $this->id;
    }

    public function description(): string {
        return $this->description;
    }

    public function verdict(): EvalVerdict {
        return $this->verdict;
    }

    public function assertions(): AssertionResults {
        return $this->assertions;
    }

    public function run(): AgentRun {
        return $this->run;
    }

    public function duration(): float {
        return $this->duration;
    }

    public function error(): ?string {
        return $this->error;
    }

    public function skipReason(): ?string {
        return $this->skipReason;
    }

    public function logs(): EvalLogs {
        return $this->logs;
    }

    /**
     * The trials behind this result, or null when the case ran once. Present
     * only for a repeated case, so nothing about a single-trial result - its
     * verdict, its console line, its serialized shape - changes when repetition
     * is available but unused.
     */
    public function repetition(): ?EvalRepetition {
        return $this->repetition;
    }

    /**
     * Per-trial results in execution order. Empty for a case that ran once: that
     * result IS its single trial, and is not duplicated inside itself.
     *
     * @return list<EvalResult>
     */
    public function trials(): array {
        return $this->repetition?->trials() ?? [];
    }

    public function trialCount(): int {
        return $this->repetition?->trialCount() ?? 1;
    }

    /** How many trials passed. A case that ran once contributes 1 when it passed and 0 otherwise. */
    public function passCount(): int {
        return $this->repetition?->passCount() ?? ($this->verdict === EvalVerdict::Passed ? 1 : 0);
    }

    /** Mean judged-assertion score across the trials; null when nothing was judged or the case ran once. */
    public function judgeScoreMean(): ?float {
        return $this->repetition?->judgeScoreMean();
    }

    /**
     * POPULATION standard deviation of the judged-assertion scores across the
     * trials - divided by N, not N-1, because the trials are the population
     * being described rather than a sample of a wider one. See
     * `EvalRepetition::judgeScoreStdDev()`. Null when nothing was judged or the
     * case ran once; 0.0, never a division by zero, for a single score.
     */
    public function judgeScoreStdDev(): ?float {
        return $this->repetition?->judgeScoreStdDev();
    }

    /**
     * What produced this eval's score: the target's own resolved LLM
     * configuration, and - when a judged assertion carries the judge's own
     * `AgentRun` - the judge's class, LLM configuration, and whether it ran
     * without a configured guard.
     *
     * `judge.class` is the real `CanJudgeAgentEval::class` observed by
     * `JudgeExpectation::resolve()` at the point it built this assertion's
     * result - never inferred from the shape of `JudgeScore`. It is reported
     * only for assertions that also carry the judge's own `AgentRun`, so a
     * lightweight judge that produced a score but no run contributes neither
     * `class` nor `llm`/`guardsWarningObserved` here.
     *
     * `judge.temperature` is always null: `AgentLoopJudge::judge()` reads the
     * built loop's `AgentProfile`, which exposes no temperature accessor once a
     * loop is built. Reporting an assumed default here would fabricate a value
     * the judge may not actually have used.
     *
     * `judge.guardsWarningObserved` is derived from the presence of a
     * `JudgeGuardsNotConfigured` event on the judge's own run, never from its
     * absence - a run with no warning event does not prove guards WERE
     * configured. `AgentLoopJudge::guardProfile()`, which does know this
     * directly, is not used here: it reflects only its instance's most recent
     * `judge()` call, which is the wrong scope once multiple judged assertions
     * share one judge instance - per-assertion event scanning is used instead.
     *
     * @return array{target: array<string, mixed>|null, judge: array{class: string|null, llm: array<string, mixed>|null, temperature: null, guardsWarningObserved: bool}|null}
     */
    public function provenance(): array {
        return [
            'target' => $this->run->llmProfile()?->toArray(),
            'judge' => $this->judgeProvenance(),
        ];
    }

    /**
     * Target and judge token totals, kept strictly separate so judge cost never
     * folds into target cost. `target` sums the target run's own usage; `judge`
     * sums usage across every judged assertion's own run (lightweight judges
     * that carry no run contribute 0).
     *
     * A repeated case sums its trials instead of reading its own representative
     * trial: N trials really did spend N trials' worth of tokens, and reporting
     * one of them would under-report the run's cost by a factor of N - the exact
     * misreport the cost split exists to prevent.
     *
     * @return array{target: int, judge: int, total: int}
     */
    public function tokens(): array {
        if ($this->repetition !== null) {
            return self::sumTokens($this->repetition->trials());
        }
        $target = $this->run->usage()->total();
        $judge = 0;
        foreach ($this->assertions->all() as $assertion) {
            $judge += $assertion->judgeScore()?->run?->usage()->total() ?? 0;
        }
        return ['target' => $target, 'judge' => $judge, 'total' => $target + $judge];
    }

    /** @return array<string, mixed> */
    public function toArray(?array $envelope = null): array {
        $data = [
            'id' => $this->id,
            'description' => $this->description,
            'verdict' => $this->verdict->value,
            'duration' => $this->duration,
            'error' => $this->error,
            'skipReason' => $this->skipReason,
            'logs' => array_map(static fn (EvalLog $log): array => $log->toArray(), $this->logs->all()),
            'assertions' => array_map(static fn (AssertionResult $result): array => $result->toArray(), $this->assertions->all()),
            'run' => $this->run->toArray(),
            'provenance' => [...$this->provenance(), ...($envelope ?? [])],
            'tokens' => $this->tokens(),
        ];
        // Appended only for a repeated case, so a single-trial document keeps
        // exactly the keys - and the key order - it had before repetition
        // existed, rather than gaining a null field every reader must ignore.
        if ($this->repetition !== null) {
            $data['repetition'] = $this->repetition->toArray();
        }
        return $data;
    }

    // INTERNAL ////////////////////////////////////////////////

    /** @param list<EvalResult> $trials @return array{target: int, judge: int, total: int} */
    private static function sumTokens(array $trials): array {
        $target = 0;
        $judge = 0;
        foreach ($trials as $trial) {
            $trialTokens = $trial->tokens();
            $target += $trialTokens['target'];
            $judge += $trialTokens['judge'];
        }
        return ['target' => $target, 'judge' => $judge, 'total' => $target + $judge];
    }

    /** @return array{class: string|null, llm: array<string, mixed>|null, temperature: null, guardsWarningObserved: bool}|null */
    private function judgeProvenance(): ?array {
        $sawJudgeRun = false;
        $judgeClass = null;
        $judgeLlm = null;
        $guardsWarningObserved = false;
        foreach ($this->assertions->all() as $assertion) {
            $run = $assertion->judgeScore()?->run;
            if ($run === null) {
                continue;
            }
            $sawJudgeRun = true;
            // $assertion->judgeClass() is null only if a judge was resolved
            // through something other than JudgeExpectation::resolve() (the
            // only path that currently sets it) - report that honestly as
            // null rather than guessing which implementation ran.
            $judgeClass ??= $assertion->judgeClass();
            $judgeLlm ??= $run->llmProfile()?->toArray();
            foreach ($run->events() as $event) {
                if ($event instanceof JudgeGuardsNotConfigured) {
                    $guardsWarningObserved = true;
                }
            }
        }
        if (!$sawJudgeRun) {
            return null;
        }
        return [
            'class' => $judgeClass,
            'llm' => $judgeLlm,
            'temperature' => null,
            'guardsWarningObserved' => $guardsWarningObserved,
        ];
    }
}
