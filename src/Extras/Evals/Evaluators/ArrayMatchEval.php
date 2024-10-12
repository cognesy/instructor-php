<?php

namespace Cognesy\Instructor\Extras\Evals\Evaluators;

use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExperiment;
use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Extras\Evals\Data\Feedback;
use Cognesy\Instructor\Extras\Evals\Data\ParameterFeedback;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Metrics\MatchCount;
use Cognesy\Instructor\Features\LLM\Data\Usage;

class ArrayMatchEval implements CanEvaluateExperiment
{
    public function __construct(
        private string $name,
        private array $expected,
    ) {}

    public function evaluate(Experiment $experiment): Evaluation {
        $feedback = new Feedback();
        $matches = 0;
        $total = 0;
        foreach($this->expected as $key => $expectedVal) {
            $total++;

            // TODO: this is not sufficient - need to handle nested arrays
            $data = json_encode($experiment->response->json());
            $actualVal = $data[$key] ?? null;

            $varFeedback = $this->getFeedback($key, $expectedVal, $actualVal);
            if ($varFeedback) {
                $feedback->add($varFeedback);
                continue;
            }
            $matches++;
        }

        return new Evaluation(
            metric: new MatchCount(matches: $matches, total: $total, name: $this->name),
            feedback: $feedback,
            usage: Usage::none(),
        );
    }

    // INTERNAL /////////////////////////////////////////////////

    private function getFeedback(string $key, mixed $expectedVal, mixed $actualVal) : ?ParameterFeedback {
        return match(true) {
            ($expectedVal !== null) && ($actualVal === null) => new ParameterFeedback(
                parameterName: $key,
                feedback: "Expected param `$key`, but param not found in actual result"
            ),
            ($actualVal !== $expectedVal) => new ParameterFeedback(
                parameterName: $key,
                feedback: "Expected value `$expectedVal`, but actual is `$actualVal`"
            ),
            default => null,
        };
    }
}
