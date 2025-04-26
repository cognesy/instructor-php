<?php

namespace Cognesy\Evals\Observers\Aggregate;

use Cognesy\Evals\Contracts\CanObserveExperiment;
use Cognesy\Evals\Enums\NumberAggregationMethod;
use Cognesy\Evals\Experiment;
use Cognesy\Evals\Observation;
use Cognesy\Evals\Observation\SelectObservations;
use Cognesy\Evals\Utils\NumberSeriesAggregator;
use InvalidArgumentException;

class AggregateExperimentObserver implements CanObserveExperiment
{
    public function __construct(
        private string $name = '',
        private string $observationKey = '',
        private array $params = [],
        private NumberAggregationMethod $method = NumberAggregationMethod::Mean,
        private bool $throwOnEmptyObservations = true,
    ) {
        if (empty($name)) {
            throw new InvalidArgumentException('Name cannot be empty');
        }
        if (empty($observationKey)) {
            throw new InvalidArgumentException('Metric name cannot be empty');
        }
    }

    public function observe(Experiment $experiment): Observation {
        return Observation::make(
            type: 'summary',
            key: $this->name,
            value: $this->calculate($experiment),
            metadata: array_filter(array_merge([
                'experimentId' => $experiment->id(),
                'aggregatedKey' => $this->observationKey,
                'aggregationMethod' => $this->method->value,
            ], $this->params)),
        );
    }

    private function calculate(Experiment $experiment) : float|int {
        $observations = SelectObservations::from([
            $experiment->observations(),
            $experiment->executionObservations(),
        ])->withKey($this->observationKey)->get();

        if (empty($observations) && $this->throwOnEmptyObservations) {
            throw new InvalidArgumentException("No observations found for key: {$this->observationKey}");
        }

        if (empty($observations)) {
            return 0;
        }

        $values = array_map(
            callback: fn($observation) => $observation->toFloat(),
            array: $observations,
        );

        return (new NumberSeriesAggregator(
            values: $values,
            params: $this->params,
            method: $this->method)
        )->aggregate();
    }
}
