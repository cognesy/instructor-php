<?php

namespace Cognesy\Instructor\Contracts;

interface CanValidateObject
{
    /**
     * Validate response object
     * @param object $dataObject
     * @return array
     */
    public function validate(object $dataObject) : array;
}
