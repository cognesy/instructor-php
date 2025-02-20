<?php

namespace Cognesy\Addons\Evals\Contracts;

use Cognesy\Addons\Evals\Execution;
use Cognesy\Addons\Evals\Observation;

interface CanObserveExecution
{
    /**
     * Observe the experiment.
     *
     * @param \Cognesy\Addons\Evals\Execution $execution
     * @return \Cognesy\Addons\Evals\Observation
     */
    public function observe(Execution $execution) : Observation;
}
