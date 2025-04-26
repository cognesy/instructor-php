<?php

namespace Cognesy\Evals\Observers\Aggregate;

use Cognesy\Evals\Contracts\CanObserveExperiment;
use Cognesy\Evals\Enums\NumberAggregationMethod;
use Cognesy\Evals\Experiment;
use Cognesy\Evals\Observation;

class ExperimentLatency implements CanObserveExperiment
{
    public function observe(Experiment $experiment): Observation {
        return (new AggregateExperimentObserver(
            name: 'experiment.latency_p95',
            observationKey: 'execution.timeElapsed',
            params: [
                'percentile' => 95,
                'unit' => 'seconds',
                'format' => '%.2f',
            ],
            method: NumberAggregationMethod::Percentile,
        ))->observe($experiment);
    }
}