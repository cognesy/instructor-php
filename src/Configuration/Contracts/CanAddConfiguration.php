<?php

namespace Cognesy\Instructor\Configuration\Contracts;

use Cognesy\Instructor\Configuration\Configuration;

interface CanAddConfiguration
{
    public function addConfiguration(Configuration $config) : void;
}