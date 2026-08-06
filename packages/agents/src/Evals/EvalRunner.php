<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use RuntimeException;
use Throwable;

final readonly class EvalRunner
{
    public function __construct(
        private ?CanRunAgentEvalTarget $target = null,
        private EvalConfig $config = new EvalConfig(),
    ) {}

    public function run(AgentEvals $suite, ?EvalRunOptions $options = null): EvalRunResult {
        $options ??= EvalRunOptions::default();
        $target = $this->target ?? $this->config->target() ?? throw new RuntimeException('Eval target is not configured.');
        $selected = $suite->filtered($options->filter(), $options->tags(), $options->excludedTags());
        $reporters = $options->skipReport() ? EvalReporters::none() : $this->config->reporters();
        if ($options->verbose()) {
            $reporters = $reporters->withVerboseConsole();
        }
        $reporterErrors = [];
        $this->report($reporters, static fn (CanReportAgentEvals $reporter) => $reporter->onRunStarted($selected->count()), $reporterErrors);
        $results = [];
        foreach ($selected as $eval) {
            $result = $this->runCase($eval, $target, $options);
            $results[] = $result;
            $this->report($reporters, static fn (CanReportAgentEvals $reporter) => $reporter->onEvalCompleted($result), $reporterErrors);
        }
        $run = new EvalRunResult($reporterErrors, $options->strict(), ...$results);
        return $this->completeReports($reporters, $run, $reporterErrors);
    }

    /**
     * One case, run `repeat` times. A single trial returns that trial's result
     * unchanged - not a one-element aggregate - so `repeat=1` is the code path it
     * always was and produces the identical result object.
     */
    private function runCase(AgentEval $eval, CanRunAgentEvalTarget $target, EvalRunOptions $options): EvalResult {
        $trials = [];
        for ($trial = 0; $trial < $options->repeat(); $trial++) {
            $trials[] = $this->runTrial($eval, $target, $options);
        }
        if (count($trials) === 1) {
            return $trials[0];
        }
        return self::aggregate($trials, $options->passRate());
    }

    /**
     * One trial of one case, against a FRESH session.
     *
     * The freshness is the measurement: a session carried across trials would
     * accumulate the previous trial's conversation, so trial 2 would grade an
     * agent that had already answered, and the observed spread would describe a
     * growing conversation rather than a repeated one. Each trial therefore
     * builds its own `EvalContext`, which opens its own session through
     * `CanRunAgentEvalTarget::open()` and collects into its own assertion and log
     * collectors - `EvalContext::newSession()`, which deliberately shares
     * collectors, is the wrong tool here.
     *
     * The cooperative timeout stays a per-trial budget, so a repeated case is not
     * failed for the wall time of N trials measured against a one-trial budget.
     */
    private function runTrial(AgentEval $eval, CanRunAgentEvalTarget $target, EvalRunOptions $options): EvalResult {
        $context = new EvalContext($target, judge: $eval->judge() ?? $this->config->judge());
        $started = microtime(true);
        $error = null;
        $skipReason = null;
        try {
            ($eval->test())($context);
        } catch (EvalSkipped $skip) {
            $skipReason = $skip->getMessage();
        } catch (EvalRequirementFailed) {
            // The failed gate is already recorded by require().
        } catch (Throwable $failure) {
            $error = $failure->getMessage();
        }
        $duration = microtime(true) - $started;
        if ($options->timeout() !== null && $duration > $options->timeout()) {
            $error ??= sprintf('Eval exceeded cooperative timeout of %.3f seconds.', $options->timeout());
        }
        $assertions = $context->assertions();
        $verdict = (new EvalVerdictResolver())->resolve($assertions, $skipReason !== null, $error);
        return new EvalResult(
            id: $eval->id() ?? 'anonymous',
            description: $eval->description(),
            verdict: $verdict,
            assertions: $assertions,
            run: $context->run(),
            duration: $duration,
            error: $error,
            skipReason: $skipReason,
            logs: $context->logs(),
        );
    }

    /**
     * Folds N trials into one result. The aggregate's assertions, target run,
     * logs, error and skip reason come from the representative trial (see
     * `EvalRepetition::representative()`) so a reporter still has one concrete
     * failure to show, while every trial is retained on the repetition itself.
     * The duration is the total across trials - the wall time the case cost.
     *
     * @param list<EvalResult> $trials
     */
    private static function aggregate(array $trials, float $passRate): EvalResult {
        $repetition = EvalRepetition::fromTrials($trials, $passRate);
        $representative = $repetition->representative();
        $duration = 0.0;
        foreach ($trials as $trial) {
            $duration += $trial->duration();
        }
        return new EvalResult(
            id: $representative->id(),
            description: $representative->description(),
            verdict: (new EvalVerdictResolver())->resolveRepeated($repetition),
            assertions: $representative->assertions(),
            run: $representative->run(),
            duration: $duration,
            error: $representative->error(),
            skipReason: $representative->skipReason(),
            logs: $representative->logs(),
            repetition: $repetition,
        );
    }

    /** @param callable(CanReportAgentEvals): void $callback @param list<string> $errors */
    private function report(EvalReporters $reporters, callable $callback, array &$errors): void {
        foreach ($reporters as $reporter) {
            try {
                $callback($reporter);
            } catch (Throwable $error) {
                $errors[] = $reporter->id() . ': ' . $error->getMessage();
            }
        }
    }

    /** @param list<string> $errors */
    private function completeReports(EvalReporters $reporters, EvalRunResult $run, array &$errors): EvalRunResult {
        foreach ($reporters as $reporter) {
            if ($reporter instanceof CanFailAgentEvalTestSuite) {
                continue;
            }
            try {
                $reporter->onRunCompleted($run);
            } catch (Throwable $error) {
                $errors[] = $reporter->id() . ': ' . $error->getMessage();
            }
        }
        $completed = new EvalRunResult($errors, $run->strict(), ...$run->all());
        foreach ($reporters as $reporter) {
            if (!$reporter instanceof CanFailAgentEvalTestSuite) {
                continue;
            }
            $reporter->onRunCompleted($completed);
        }
        return $completed;
    }
}
