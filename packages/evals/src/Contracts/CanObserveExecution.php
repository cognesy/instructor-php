<?php

namespace Cognesy\Evals\Contracts;

use Cognesy\Evals\Execution;
use Cognesy\Evals\Observation;

interface CanObserveExecution
{
    /**
     * Observe the experiment.
     *
     * @param \Cognesy\Evals\Execution $execution
     * @return \Cognesy\Evals\Observation
     */
    public function observe(Execution $execution) : Observation;
}
