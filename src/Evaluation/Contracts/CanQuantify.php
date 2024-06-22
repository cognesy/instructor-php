<?php

namespace Cognesy\Instructor\Evaluation\Contracts;

use Cognesy\Instructor\Evaluation\Data\Evaluation;

interface CanQuantify
{
    public function quantify(Evaluation $evaluation) : Metric;
}
