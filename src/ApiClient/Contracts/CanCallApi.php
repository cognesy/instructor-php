<?php

namespace Cognesy\Instructor\ApiClient\Contracts;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Data\Responses\PartialApiResponse;
use Generator;

interface CanCallApi
{
    public function get() : ApiResponse;

    /** @returns Generator<PartialApiResponse>  */
    public function stream() : Generator;

}