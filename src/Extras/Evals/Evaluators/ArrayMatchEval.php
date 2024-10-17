<?php

namespace Cognesy\Instructor\Extras\Evals\Evaluators;

use Adbar\Dot;
use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExecution;
use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Extras\Evals\Data\Feedback;
use Cognesy\Instructor\Extras\Evals\Data\ParameterFeedback;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Metrics\Generic\MatchCount;
use Cognesy\Instructor\Extras\Evals\Utils\CompareNestedArrays;
use Cognesy\Instructor\Features\LLM\Data\Usage;

class ArrayMatchEval implements CanEvaluateExecution
{
    public function __construct(
        private string $name,
        private array $expected,
    ) {}

    public function evaluate(Execution $execution): Evaluation {
        $data = $execution->response->json()->toArray();
        $differences = (new CompareNestedArrays)->compare($this->expected, $data);
        $total = count((new Dot($data))->flatten());
        $matches = $total - count($differences);
        return new Evaluation(
            metric: new MatchCount(matches: $matches, total: $total, name: $this->name),
            feedback: $this->makeFeedback($differences) ?? Feedback::none(),
            usage: Usage::none(),
        );
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeFeedback(array $differences) : Feedback {
        $feedback = new Feedback();
        foreach($differences as $path => $diff) {
            $varFeedback = $this->getFeedback($path, $diff['expected'], $diff['actual']);
            if ($varFeedback) {
                $feedback->add($varFeedback);
            }
        }
        return $feedback;
    }

    private function getFeedback(string $key, mixed $expectedVal, mixed $actualVal) : ?ParameterFeedback {
        return match(true) {
            ($expectedVal !== null) && ($actualVal === null) => new ParameterFeedback(
                parameterName: $key,
                feedback: "Expected `$key`, but param not found in result"
            ),
            ($actualVal !== $expectedVal) => new ParameterFeedback(
                parameterName: $key,
                feedback: "Expected `$key` value `$expectedVal`, but actual is `$actualVal`"
            ),
            default => null,
        };
    }
}
