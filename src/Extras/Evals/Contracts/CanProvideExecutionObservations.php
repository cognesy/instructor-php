<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Observation;

interface CanProvideExecutionObservations
{
    /**
     * Generates observations
     *
     * @return iterable<Observation>
     */
    public function observations(Execution $subject): iterable;
}
