<?php

namespace Cognesy\Polyglot\Http\Contracts;

interface CanHandleRequestPool
{
    public function pool(array $requests, ?int $maxConcurrent = null): array;
}