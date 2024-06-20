<?php

namespace Cognesy\Instructor\Transformation\Contracts;

/**
 * Can transform an object into a different format, eg. into a scalar value.
 */
interface CanTransformObject
{
    public function transform(mixed $data): mixed;
}
