<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Observation;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
interface Observer
{
    public static function start(string $key) : static;
    public function with(array $metadata = []) : static;
    public function reset() : static;
    public function make(mixed $value = null) : Observation;
}
