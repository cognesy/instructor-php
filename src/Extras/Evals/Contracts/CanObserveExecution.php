<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Observation;

interface CanObserveExecution
{
    /**
     * Summarize the experiment.
     *
     * @param Execution $execution
     * @return Observation
     */
    public function observe(Execution $execution) : Observation;
}
