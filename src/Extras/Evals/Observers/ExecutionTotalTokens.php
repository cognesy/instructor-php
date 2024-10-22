<?php

namespace Cognesy\Instructor\Extras\Evals\Observers;

use Cognesy\Instructor\Extras\Evals\Contracts\CanObserveExecution;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Observation;

class ExecutionTotalTokens implements CanObserveExecution
{
    public function observe(Execution $execution): Observation {
        return Observation::make(
            type: 'metric',
            key: 'execution.totalTokens',
            value: $execution->usage()->total(),
            metadata: [
                'executionId' => $execution->id(),
                'unit' => 'tokens',
                'format' => '%d tokens',
            ],
        );
    }
}
