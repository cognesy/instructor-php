<?php

namespace Cognesy\Instructor\Contracts;

interface CanTransformResponse
{
    public function transform() : mixed;
}