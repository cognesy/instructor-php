<?php declare(strict_types=1);

namespace Cognesy\Evals\Contracts;

use Cognesy\Evals\Execution;
use Cognesy\Evals\Observation;

interface CanObserveExecution
{
    /**
     * Observe the experiment.
     *
     * @param Execution $execution
     * @return Observation
     */
    public function observe(Execution $execution) : Observation;
}
