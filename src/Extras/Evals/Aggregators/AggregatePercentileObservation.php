<?php

namespace Cognesy\Instructor\Extras\Evals\Aggregators;

use Cognesy\Instructor\Extras\Evals\Contracts\CanAggregateObservations;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Utils\NumberSeriesAggregator;
use Cognesy\Instructor\Extras\Evals\Enums\NumberAggregationMethod;
use InvalidArgumentException;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class AggregatePercentileObservation implements CanAggregateObservations
{
    public function __construct(
        private string $name,
        private string $observationKey,
        private int $percentile = 95,
    ) {
        if (empty($name)) {
            throw new InvalidArgumentException('Name cannot be empty');
        }
        if ($percentile < 0 || $percentile > 100) {
            throw new InvalidArgumentException('Percentile has to be between 0 and 100');
        }
    }

    public function name(): string {
        return $this->name;
    }

    public function aggregate(Experiment $experiment): float {
        return (new NumberSeriesAggregator(
            values: array_map(
                callback: fn($observation) => $observation->toFloat(),
                array: $experiment->observations($this->observationKey)
            ),
            params: ['percentile' => $this->percentile],
            method: NumberAggregationMethod::Percentile
        ))->aggregate();
    }
}
