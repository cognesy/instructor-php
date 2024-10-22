<?php

namespace Cognesy\Evals\SimpleExtraction;

use Cognesy\Instructor\Extras\Evals\Contracts\CanObserveExecution;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Observation;

class CompanyEval implements CanObserveExecution
{
    private array $expectations;

    public function __construct(array $expectations) {
        $this->expectations = $expectations;
    }

    public function observe(Execution $execution): Observation {
        $company = $execution->data()->get('response')?->value();
        $isCorrect = ($this->expectations['name'] === ($company->name ?? null))
            && ($this->expectations['year'] === ($company->year ?? null));

        return Observation::make(
            type: 'metric',
            key: 'execution.is_correct',
            value: $isCorrect,
            metadata: [
                'executionId' => $execution->id(),
                'data' => $company->toArray(),
            ],
        );
    }
}
