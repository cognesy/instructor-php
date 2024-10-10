<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

interface Metric
{
    public function value() : mixed;
    public function toLoss() : float;
    public function toScore() : float;
}