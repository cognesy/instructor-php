<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Execution;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
interface CanMeasureExecution
{
    public function measure(Execution $execution) : Metric;
}