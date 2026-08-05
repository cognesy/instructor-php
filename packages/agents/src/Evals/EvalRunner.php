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
            $result = new EvalResult(
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
            $results[] = $result;
            $this->report($reporters, static fn (CanReportAgentEvals $reporter) => $reporter->onEvalCompleted($result), $reporterErrors);
        }
        $run = new EvalRunResult($reporterErrors, $options->strict(), ...$results);
        return $this->completeReports($reporters, $run, $reporterErrors);
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
