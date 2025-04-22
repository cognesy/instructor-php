<?php

namespace Cognesy\Addons\Evals\Contracts;

use Cognesy\Addons\Evals\Experiment;
use Cognesy\Addons\Evals\Observation;

interface CanObserveExperiment
{
    /**
     * Observe the experiment.
     *
     * @param Experiment $experiment
     * @return Observation
     */
    public function observe(Experiment $experiment) : Observation;
}