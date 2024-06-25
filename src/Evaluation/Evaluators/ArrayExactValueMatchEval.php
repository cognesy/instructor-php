<?php
namespace Cognesy\Instructor\Evaluation\Evaluators;

use Cognesy\Instructor\Evaluation\Contracts\CanEvaluate;
use Cognesy\Instructor\Evaluation\Contracts\Metric;
use Cognesy\Instructor\Evaluation\Data\PromptEvaluation;
use Cognesy\Instructor\Evaluation\Data\EvaluationResult;
use Cognesy\Instructor\Evaluation\Data\Feedback;
use Cognesy\Instructor\Evaluation\Data\ParameterFeedback;
use Cognesy\Instructor\Evaluation\Metrics\ExactValueMatch;

class ArrayExactValueMatchEval implements CanEvaluate
{
    private Feedback $feedback;
    private Metric $metric;

    public function process(PromptEvaluation $evaluation): EvaluationResult {
        $this->feedback = new Feedback();
        $matches = 0;
        $total = 0;
        foreach($evaluation->expectedResult as $key => $value) {
            $total++;
            $actualVal = $evaluation->actualResult[$key] ?? null;
            $varFeedback = match(true) {
                $actualVal === null => new ParameterFeedback($key,
                    "Expected param `$key`, but param not found in actual result"
                ),
                $value !== $evaluation->actualResult => new ParameterFeedback($key,
                    "Expected value `$value`, but actual is `$actualVal`"
                ),
                default => null,
            };
            if ($varFeedback) {
                $this->feedback->add($varFeedback);
                continue;
            }
            $matches++;
        }
        $this->metric = new ExactValueMatch($matches, $total);
        return new EvaluationResult(
            metric: $this->metric,
            feedback: $this->feedback,
        );
    }
}