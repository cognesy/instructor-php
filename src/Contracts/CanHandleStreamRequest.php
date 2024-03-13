<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Core\Data\Request;
use Generator;

interface CanHandleStreamRequest
{
    public function respondTo(Request $request) : Generator;
}