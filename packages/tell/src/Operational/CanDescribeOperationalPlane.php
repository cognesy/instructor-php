<?php

declare(strict_types=1);

namespace Cognesy\Tell\Operational;

interface CanDescribeOperationalPlane
{
    public function planeOperation(): PlaneOperation;
}
