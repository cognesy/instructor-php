<?php declare(strict_types=1);

namespace Cognesy\Evals\Contracts;

use Cognesy\Evals\Experiment;
use Cognesy\Evals\Observation;

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