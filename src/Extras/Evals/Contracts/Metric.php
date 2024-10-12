<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

interface Metric
{
    public function name() : string;
    public function value() : mixed;
    public function toLoss() : float;
    public function toScore() : float;
    public function toString () : string;
}
