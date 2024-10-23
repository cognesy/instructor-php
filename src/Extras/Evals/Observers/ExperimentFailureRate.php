<?php

namespace Cognesy\Instructor\Extras\Evals\Observers;

use Cognesy\Instructor\Extras\Evals\Contracts\CanObserveExperiment;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Observation;

class ExperimentFailureRate implements CanObserveExperiment
{
    public function observe(Experiment $experiment): Observation {
        return Observation::make(
            type: 'metric',
            key: 'experiment.failureRate',
            value: $this->metrics($experiment)->failureRate,
            metadata: [
                'experimentId' => $experiment->id(),
                'unit' => 'fraction',
                'format' => '%d.2',
                'failed' => $this->metrics($experiment)->failed,
                'total' => $this->metrics($experiment)->total,
                'aggregationMethod' => 'mean',
            ],
        );
    }

    private function metrics(Experiment $experiment) : object {
        $executionCount = count($experiment->executions());
        $executionsFailed = array_reduce($experiment->executions(), function ($carry, $execution) {
            return $carry + ($execution->status() === 'failed' ? 1 : 0);
        }, 0);
        $failureRate = ($executionCount === 0)
            ? 0
            : ($executionsFailed / $executionCount);

        return new class($failureRate, $executionCount, $executionsFailed) {
            public function __construct(
                public float $failureRate,
                public int $total,
                public int $failed,
            ) {}
        };
    }
}
