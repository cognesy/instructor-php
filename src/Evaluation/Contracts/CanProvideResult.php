<?php

namespace Cognesy\Instructor\Evaluation\Contracts;

interface CanProvideResult
{
    public function resultFor(array $input) : array;
}