<?php

namespace Cognesy\Instructor\Evaluation\Contracts;

interface Metric
{
    public function value() : mixed;
    public function toLoss() : float;
    public function toScore() : float;
}
