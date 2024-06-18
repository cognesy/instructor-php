<?php

namespace Cognesy\Instructor\Evaluation;

use Cognesy\Instructor\Evaluation\Data\Evaluation;

class Evaluator
{
    public function __construct(
        private Evaluation $evaluation,
    ) {
    }

    public function evaluate() : static {
    }
}