<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Observation;

interface CanObserveExperiment
{
    /**
     * Summarize the experiment.
     *
     * @param Experiment $experiment
     * @return Observation
     */
    public function observe(Experiment $experiment) : Observation;
}