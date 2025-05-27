<?php

namespace Cognesy\Instructor\Transformation\Contracts;

/**
 * Response model can transform itself into a different format, eg. into a scalar value.
 */
interface CanTransformSelf
{
    public function transform() : mixed;
}