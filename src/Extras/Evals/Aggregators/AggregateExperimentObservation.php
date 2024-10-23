<?php

namespace Cognesy\Instructor\Extras\Evals\Aggregators;

use Cognesy\Instructor\Extras\Evals\Contracts\CanObserveExperiment;
use Cognesy\Instructor\Extras\Evals\Enums\NumberAggregationMethod;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Observation;
use Cognesy\Instructor\Extras\Evals\Observation\SelectObservations;
use Cognesy\Instructor\Extras\Evals\Utils\NumberSeriesAggregator;
use InvalidArgumentException;

class AggregateExperimentObservation implements CanObserveExperiment
{
    public function __construct(
        private string $name = '',
        private string $observationKey = '',
        private array $params = [],
        private NumberAggregationMethod $method = NumberAggregationMethod::Mean,
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
