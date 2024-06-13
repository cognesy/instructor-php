<?php

namespace Cognesy\Instructor\Configuration\Contracts;

use Cognesy\Instructor\Configuration\Configuration;

interface CanProvideConfiguration
{
    public function toConfiguration(): Configuration;
}