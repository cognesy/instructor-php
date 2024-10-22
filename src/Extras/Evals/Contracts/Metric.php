<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use JetBrains\PhpStorm\Deprecated;
use JsonSerializable;

#[Deprecated]
interface Metric extends JsonSerializable
{
    public function name() : string;
    public function value() : mixed;
    public function unit() : Unit;
    public function toString() : string;
    public function toFloat() : float;
    public function toCliColor() : array;
}
