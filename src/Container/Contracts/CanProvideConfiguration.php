<?php

namespace Cognesy\Instructor\Container\Contracts;

use Cognesy\Instructor\Container\Container;

interface CanProvideConfiguration
{
    public function toConfiguration(): Container;
}