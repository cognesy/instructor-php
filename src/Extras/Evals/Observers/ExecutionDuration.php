<?php

namespace Cognesy\Instructor\Extras\Evals\Observers;

use Cognesy\Instructor\Extras\Evals\Contracts\CanObserveExecution;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Observation;

class ExecutionDuration implements CanObserveExecution
{
    public function observe(Execution $execution): Observation {
        return Observation::make(
            type: 'metric',
            key: 'execution.timeElapsed',
            value: $execution->timeElapsed(),
            metadata: [
                'executionId' => $execution->id(),
                'unit' => 'seconds',
                'format' => '%.2f sec',
            ],
        );
    }
}