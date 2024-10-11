<?php

namespace Cognesy\Evals\SimpleExtraction;

use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExperiment;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Metrics\BooleanCorrectness;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;

class CompanyEval implements CanEvaluateExperiment
{
    private array $expectations;

    public function __construct(array $expectations) {
        $this->expectations = $expectations;
    }

    public function evaluate(Experiment $experiment) : Metric {
        $company = $experiment->response->value();
        $isCorrect = $company->name === $this->expectations['name']
            && $company->foundingYear === $this->expectations['foundingYear'];
        return new BooleanCorrectness($isCorrect);
    }
}
