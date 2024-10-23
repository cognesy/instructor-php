<?php

namespace Cognesy\Instructor\Extras\Evals\Observers;

use Cognesy\Instructor\Extras\Evals\Contracts\CanObserveExperiment;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Observation;

class ExperimentDuration implements CanObserveExperiment
{
    public function observe(Experiment $experiment): Observation {
        return Observation::make(
            type: 'metric',
            key: 'experiment.timeElapsed',
            value: $experiment->timeElapsed(),
            metadata: [
                'experimentId' => $experiment->id(),
                'unit' => 'seconds',
                'format' => '%.2f',
                'aggregationMethod' => 'sum',
            ],
        );
    }
}
