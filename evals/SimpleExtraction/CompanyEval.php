<?php

namespace Cognesy\Evals\SimpleExtraction;

use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExperiment;
use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Extras\Evals\Data\Feedback;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Metrics\BooleanCorrectness;
use Cognesy\Instructor\Features\LLM\Data\Usage;

class CompanyEval implements CanEvaluateExperiment
{
    private array $expectations;

    public function __construct(array $expectations) {
        $this->expectations = $expectations;
    }

    public function evaluate(Experiment $experiment) : Evaluation {
        $company = $experiment->response->value();
        $isCorrect = $company->name === $this->expectations['name']
            && $company->foundingYear === $this->expectations['foundingYear'];
        return new Evaluation(
            metric: new BooleanCorrectness('is_correct', $isCorrect),
            feedback: Feedback::none(),
            usage: Usage::none(),
        );
    }
}
