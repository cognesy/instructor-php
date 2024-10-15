<?php

namespace Cognesy\Evals\SimpleExtraction;

use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExecution;
use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Extras\Evals\Data\Feedback;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Metrics\BooleanCorrectness;
use Cognesy\Instructor\Features\LLM\Data\Usage;

class CompanyEval implements CanEvaluateExecution
{
    private array $expectations;

    public function __construct(array $expectations) {
        $this->expectations = $expectations;
    }

    public function evaluate(Execution $execution) : Evaluation {
        $company = $execution->response->value();
        $isCorrect = $company->name === $this->expectations['name']
            && $company->year === $this->expectations['year'];
        return new Evaluation(
            metric: new BooleanCorrectness('is_correct', $isCorrect),
            feedback: Feedback::none(),
            usage: Usage::none(),
        );
    }
}
