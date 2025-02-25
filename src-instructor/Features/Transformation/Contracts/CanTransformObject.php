<?php

namespace Cognesy\Instructor\Features\Transformation\Contracts;

/**
 * Can transform an object into a different format, eg. into a scalar value.
 */
interface CanTransformObject
{
    /**
     * Transform the given data into a different format
     * or throw an exception if the transformation fails.
     *
     * @param mixed $data
     * @return mixed
     */
    public function transform(mixed $data): mixed;
}
