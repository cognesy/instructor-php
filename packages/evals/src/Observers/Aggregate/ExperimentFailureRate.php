<?php

namespace Cognesy\Evals\Observers\Aggregate;

use Cognesy\Evals\Contracts\CanObserveExperiment;
use Cognesy\Evals\Experiment;
use Cognesy\Evals\Observation;

class ExperimentFailureRate implements CanObserveExperiment
{
    /**
     * Observes the given experiment to record its failure rate and other related metrics.
     *
     * @param Experiment $experiment The experiment to observe.
     * @return Observation The observation containing the experiment's failure rate and metadata.
     */
    public function observe(Experiment $experiment): Observation {
        return Observation::make(
            type: 'summary',
            key: 'experiment.failureRate',
            value: $this->metrics($experiment)->failureRate,
            metadata: [
                'experimentId' => $experiment->id(),
                'unit' => 'fraction',
                'format' => '%.2f',
                'failed' => $this->metrics($experiment)->failed,
                'total' => $this->metrics($experiment)->total,
                'aggregationMethod' => 'mean',
            ],
        );
    }

    /**
     * Calculates and returns the metrics for the given experiment, including
     * failure rate, total executions, and failed executions.
     *
     * @param Experiment $experiment The experiment instance from which metrics are calculated.
     *
     * @return object An anonymous object containing failureRate, total, and failed properties.
     */
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
