<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Experiment;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
interface CanAggregateObservations
{
    public function name(): string;
    public function aggregate(Experiment $experiment): float;
}
