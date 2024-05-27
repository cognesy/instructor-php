<?php

namespace Cognesy\Instructor\Contracts\DataModel;

use Cognesy\Instructor\Schema\Data\TypeDetails;

interface CanDescribeField
{
    public function name(): string;

    public function description(): string;

    public function typeDetails(): TypeDetails;
}