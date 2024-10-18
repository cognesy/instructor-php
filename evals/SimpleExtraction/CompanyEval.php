<?php

namespace Cognesy\Evals\SimpleExtraction;

use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExecution;
use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Extras\Evals\Data\Feedback;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Metrics\Correctness\BooleanCorrectness;
use Cognesy\Instructor\Features\LLM\Data\Usage;

class CompanyEval implements CanEvaluateExecution
{
    private array $expectations;

    public function __construct(array $expectations) {
        $this->expectations = $expectations;
    }

    public function evaluate(Execution $execution) : Evaluation {
        $company = $execution->data()->get('response')?->value();
        $isCorrect = ($this->expectations['name'] === ($company->name ?? null))
            && ($this->expectations['year'] === ($company->year ?? null));
        return new Evaluation(
            metric: new BooleanCorrectness(name: 'is_correct', value: $isCorrect),
            feedback: Feedback::none(),
            usage: Usage::none(),
        );
    }
}
