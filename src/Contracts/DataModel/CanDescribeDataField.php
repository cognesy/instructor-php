<?php

namespace Cognesy\Instructor\Contracts\DataModel;

use Cognesy\Instructor\Schema\Data\TypeDetails;

interface CanDescribeDataField
{
    public function name(): string;
    public function description(): string;
    public function typeDetails(): TypeDetails;
}