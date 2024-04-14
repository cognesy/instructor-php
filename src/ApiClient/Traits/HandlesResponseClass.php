<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Data\Responses\PartialApiResponse;
use Saloon\Http\Response;

trait HandlesResponseClass
{
    /** @var class-string */
    protected string $responseClass;

    protected function makeResponse(Response $response) : ApiResponse {
        return ($this->responseClass)::fromResponse($response);
    }

    protected function makePartialResponse(string $partialData) : PartialApiResponse {
        return ($this->responseClass)::fromPartialResponse($partialData);
    }
}