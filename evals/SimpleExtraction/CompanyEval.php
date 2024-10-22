<?php

namespace Cognesy\Evals\SimpleExtraction;

use Cognesy\Instructor\Extras\Evals\Contracts\CanMeasureExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Metrics\Correctness\BooleanCorrectness;

class CompanyEval implements CanMeasureExecution
{
    private array $expectations;

    public function __construct(array $expectations) {
        $this->expectations = $expectations;
    }

    public function measure(Execution $execution): Metric {
        $company = $execution->data()->get('response')?->value();
        $isCorrect = ($this->expectations['name'] === ($company->name ?? null))
            && ($this->expectations['year'] === ($company->year ?? null));
        return new BooleanCorrectness(name: 'execution.is_correct', value: $isCorrect);
    }
}
