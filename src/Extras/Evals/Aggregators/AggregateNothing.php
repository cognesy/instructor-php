<?php

namespace Cognesy\Instructor\Extras\Evals\Aggregators;

use Cognesy\Instructor\Extras\Evals\Contracts\CanAggregateMetric;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Metrics\Generic\NullMetric;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class AggregateNothing implements CanAggregateMetric
{
    public function __construct(
        private string $name
    ) {}

    public function name(): string {
        return $this->name;
    }

    public function aggregate(Experiment $experiment): Metric {
        return new NullMetric();
    }
}