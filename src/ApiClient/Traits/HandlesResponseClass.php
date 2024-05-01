<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use JetBrains\PhpStorm\Deprecated;
use Saloon\Http\Response;

#[Deprecated]
trait HandlesResponseClass
{
    /** @var class-string */
    protected string $responseClass;
    /** @var class-string */
    protected string $partialResponseClass;

    protected function makeResponse(Response $response) : ApiResponse {
        return $this->request->toApiResponse($response);
        //return ($this->responseClass)::fromResponse($response);
    }

    protected function makePartialResponse(string $partialData) : PartialApiResponse {
        //return ($this->partialResponseClass)::fromPartialResponse($partialData);
        return $this->request->toPartialApiResponse($partialData);
    }
}