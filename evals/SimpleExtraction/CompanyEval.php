<?php

namespace Cognesy\Evals\SimpleExtraction;

use Cognesy\Addons\Evals\Contracts\CanObserveExecution;
use Cognesy\Addons\Evals\Execution;
use Cognesy\Addons\Evals\Observation;

class CompanyEval implements CanObserveExecution
{
    private string $key;
    private array $expectations;

    public function __construct(string $key, array $expectations) {
        $this->key = $key;
        $this->expectations = $expectations;
    }

    public function observe(Execution $execution): Observation {
        $company = $execution->data()->get('response')?->value();
        $isCorrect = ($this->expectations['name'] === ($company->name ?? null))
            && ($this->expectations['year'] === ($company->year ?? null));

        return Observation::make(
            type: 'metric',
            key: $this->key,
            value: $isCorrect ? 1 : 0,
            metadata: [
                'executionId' => $execution->id(),
                'data' => json_encode($company),
            ],
        );
    }
}
