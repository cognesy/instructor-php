<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\Request;

interface CanHandleSyncRequest
{
    public function responseFor(Request $request) : mixed;
}