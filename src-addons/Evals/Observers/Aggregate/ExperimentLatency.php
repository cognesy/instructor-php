<?php

namespace Cognesy\Addons\Evals\Observers\Aggregate;

use Cognesy\Addons\Evals\Contracts\CanObserveExperiment;
use Cognesy\Addons\Evals\Enums\NumberAggregationMethod;
use Cognesy\Addons\Evals\Experiment;
use Cognesy\Addons\Evals\Observation;

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