<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Observation;

interface CanSummarizeExperiment
{
    /**
     * Summarize the experiment.
     *
     * @param Experiment $experiment
     * @return Observation
     */
    public function summarize(Experiment $experiment): Observation;
}
