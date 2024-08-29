<?php

namespace Cognesy\Instructor\ApiClient\Contracts;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Enums\Mode;
use Generator;
use Saloon\Enums\Method;

interface CanCallApi
{
    public function request(array $body, string $endpoint, Method $method): static;
    public function respond(ApiRequest $request) : ApiResponse;
    public function get() : ApiResponse;
    /** @return Generator<PartialApiResponse>  */
    public function stream() : Generator;

    public function getModeRequestClass(Mode $mode) : string;
    public function withApiRequest(ApiRequest $request) : static;

    public function defaultMaxTokens() : int;
    public function defaultModel() : string;
}
