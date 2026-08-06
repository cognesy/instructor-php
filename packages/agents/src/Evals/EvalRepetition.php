<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use InvalidArgumentException;

/**
 * The N trials of one repeated eval case, with the pass count and judge-score
 * spread derived from them.
 *
 * A single run of a stochastic target graded by a stochastic judge is one draw
 * from a distribution, so a threshold applied to it says nothing about the
 * distribution's spread. This type is the record of the sample: the individual
 * trial results are kept, not just their aggregate, so an artifact reader can
 * still see which trial failed and why.
 *
 * Present only when a case ran more than once. A case that ran once has no
 * repetition object at all, so its result, its console line and its serialized
 * form are exactly what they were before repetition existed.
 */
final readonly class EvalRepetition
{
    /** Decimal places kept on the derived mean and deviation, so repeated runs of the same scores serialize identically instead of carrying binary-float noise. */
    private const PRECISION = 6;

    /**
     * @param list<EvalResult> $trials In execution order.
     * @param list<float> $judgeScores Every judged assertion's score, across every trial, in trial-then-assertion order.
     */
    private function __construct(
        private array $trials,
        private int $passCount,
        private int $requiredPasses,
        private array $judgeScores,
    ) {}

    /** @param list<EvalResult> $trials In execution order. */
    public static function fromTrials(array $trials, float $passRate): self {
        if ($trials === []) {
            throw new InvalidArgumentException('A repeated eval needs at least one trial.');
        }
        $passCount = 0;
        $judgeScores = [];
        foreach ($trials as $trial) {
            if ($trial->verdict() === EvalVerdict::Passed) {
                $passCount++;
            }
            foreach ($trial->assertions()->all() as $assertion) {
                $judgeScore = $assertion->judgeScore();
                if ($judgeScore !== null) {
                    $judgeScores[] = $judgeScore->score;
                }
            }
        }
        return new self($trials, $passCount, EvalVerdictResolver::requiredPasses(count($trials), $passRate), $judgeScores);
    }

    /** @return list<EvalResult> */
    public function trials(): array {
        return $this->trials;
    }

    public function trialCount(): int {
        return count($this->trials);
    }

    public function passCount(): int {
        return $this->passCount;
    }

    public function requiredPasses(): int {
        return $this->requiredPasses;
    }

    public function satisfied(): bool {
        return $this->passCount >= $this->requiredPasses;
    }

    public function allSkipped(): bool {
        foreach ($this->trials as $trial) {
            if ($trial->verdict() !== EvalVerdict::Skipped) {
                return false;
            }
        }
        return true;
    }

    /**
     * Mean of every judged assertion's score across every trial - one score per
     * judged assertion per trial, so a case with a single judged assertion means
     * over the trials. Null when no assertion in any trial was judged, which is
     * reported as absence rather than as 0.0.
     */
    public function judgeScoreMean(): ?float {
        if ($this->judgeScores === []) {
            return null;
        }
        return round(array_sum($this->judgeScores) / count($this->judgeScores), self::PRECISION);
    }

    /**
     * POPULATION standard deviation of the judge scores - the sum of squared
     * deviations is divided by N, not by N-1. The trials ARE the population
     * being described (this is the observed spread of the sample that produced
     * this verdict, not an estimate of a wider population's spread), and with
     * N=5 the two conventions differ by about 12%, so the choice is stated
     * rather than left to the reader.
     *
     * A single score yields 0.0, never a division by zero. Null when no
     * assertion in any trial was judged.
     */
    public function judgeScoreStdDev(): ?float {
        $count = count($this->judgeScores);
        if ($count === 0) {
            return null;
        }
        if ($count === 1) {
            return 0.0;
        }
        $mean = array_sum($this->judgeScores) / $count;
        $sumOfSquares = 0.0;
        foreach ($this->judgeScores as $score) {
            $sumOfSquares += ($score - $mean) ** 2;
        }
        return round(sqrt($sumOfSquares / $count), self::PRECISION);
    }

    /**
     * The trial whose detail is worth showing next to the aggregate: the first
     * trial that did not pass, or the first trial when they all passed. A
     * repeated case reports one set of assertions, one target run and one log
     * stream in the aggregate result, and for a 4-of-5 pass the useful one is the
     * trial that failed - the other four are already summarized by the rate.
     */
    public function representative(): EvalResult {
        foreach ($this->trials as $trial) {
            if ($trial->verdict() !== EvalVerdict::Passed) {
                return $trial;
            }
        }
        return $this->trials[0];
    }

    /**
     * Per-trial detail is kept here in full for assertions - each trial's own
     * assertion results survive into artifacts, not only the aggregate - while
     * the trial's target trace is left to the per-trial artifact files so a
     * 5-trial case does not serialize five traces into one document.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'trials' => $this->trialCount(),
            'passed' => $this->passCount,
            'required' => $this->requiredPasses,
            'satisfied' => $this->satisfied(),
            'judgeScoreMean' => $this->judgeScoreMean(),
            'judgeScoreStdDev' => $this->judgeScoreStdDev(),
            'results' => array_values(array_map(static fn (int $index, EvalResult $trial): array => [
                'trial' => $index + 1,
                'verdict' => $trial->verdict()->value,
                'duration' => $trial->duration(),
                'error' => $trial->error(),
                'skipReason' => $trial->skipReason(),
                'assertions' => array_map(static fn (AssertionResult $result): array => $result->toArray(), $trial->assertions()->all()),
                'tokens' => $trial->tokens(),
            ], array_keys($this->trials), $this->trials)),
        ];
    }
}
