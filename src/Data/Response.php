<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;

class Response {
    private ApiResponse $apiResponse;
    /** @var PartialApiResponse[] */
    private array $partialApiResponses = [];

    private mixed $returnedValue;
}
