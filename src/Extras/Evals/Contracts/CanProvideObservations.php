<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Observation;

interface CanProvideObservations
{
    /**
     * Generates observations
     *
     * @return iterable<Observation>
     */
    public function observations(): iterable;
}
