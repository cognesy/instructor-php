<?php

namespace Cognesy\Instructor\Container\Contracts;

use Cognesy\Instructor\Container\Container;

interface CanAddConfiguration
{
    public function addConfiguration(Container $config) : void;
}