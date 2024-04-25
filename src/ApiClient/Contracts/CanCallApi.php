<?php

namespace Cognesy\Instructor\ApiClient\Contracts;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Generator;

interface CanCallApi
{
    public function get() : ApiResponse;

    /** @returns Generator<\Cognesy\Instructor\ApiClient\Responses\PartialApiResponse>  */
    public function stream() : Generator;

}