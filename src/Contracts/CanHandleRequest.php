<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Core\Data\Request;

interface CanHandleRequest
{
    public function respondTo(Request $request) : mixed;
}