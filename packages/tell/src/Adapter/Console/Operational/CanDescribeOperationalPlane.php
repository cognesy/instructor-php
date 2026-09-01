<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Operational;

interface CanDescribeOperationalPlane
{
    public function planeOperation(): PlaneOperation;
}
