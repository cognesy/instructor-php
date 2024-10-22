<?php

namespace Cognesy\Instructor\Extras\Evals\Observers;

use Cognesy\Instructor\Extras\Evals\Contracts\CanObserveExperiment;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Observation;

class ExperimentTotalTokens implements CanObserveExperiment
{
    public function observe(Experiment $experiment): Observation {
        return Observation::make(
            type: 'metric',
            key: 'experiment.totalTokens',
            value: $experiment->usage()->total(),
            metadata: [
                'experimentId' => $experiment->id(),
                'unit' => 'tokens',
                'format' => '%d tokens',
            ],
        );
    }
}
