<?php
namespace Cognesy\Instructor\Evaluation\Evaluators;

use Cognesy\Instructor\Evaluation\Contracts\CanEvaluateResult;
use Cognesy\Instructor\Evaluation\Contracts\CanProvideFeedback;
use Cognesy\Instructor\Evaluation\Data\Evaluation;
use Cognesy\Instructor\Evaluation\Data\Feedback;
use Cognesy\Instructor\Evaluation\Data\VariableFeedback;
use Cognesy\Instructor\Evaluation\Metrics\ExactValueMatch;

class ArrayExactValueMatchEval implements CanEvaluateResult, CanProvideFeedback
{
    public function process(Evaluation $evaluation): Evaluation {
        $expected = $evaluation->expected;
        $actual = $evaluation->actual;
        $evaluation->metric = $this->evaluate($expected, $actual);
        $evaluation->feedback = $this->feedback();
    }

    private function evaluate(array $actual, array $expected = []) : ExactValueMatch {
        $mismatches = [];
        $matches = 0;
        $total = 0;
        foreach($expected as $key => $value) {
            $total++;

            $actualVal = $actual[$key] ?? null;
            $mismatch = match(true) {
                $actualVal === null => new VariableFeedback($key,
                    "Expected param `$key`, but param not found in actual result"
                ),
                $value !== $actual => new VariableFeedback($key,
                    "Expected value `$value`, but actual is `$actualVal`"
                ),
                default => null,
            };

            if ($mismatch) {
                $this->mismatches[] = $mismatch;
                continue;
            }

            $matches++;
        }
        return new ExactValueMatch($matches, $total);
    }

    public function feedback(): Feedback {
        return new Feedback($this->mismatches);
    }
}