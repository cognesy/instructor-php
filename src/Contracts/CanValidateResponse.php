<?php

namespace Cognesy\Instructor\Contracts;

interface CanValidateResponse
{
    /**
     * Validate response object
     * @param object $response
     * @return array
     */
    public function validate(object $response) : array;
}
