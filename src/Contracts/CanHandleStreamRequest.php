<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\Request;
use Generator;

interface CanHandleStreamRequest
{
    public function streamResponseFor(Request $request) : Generator;
}