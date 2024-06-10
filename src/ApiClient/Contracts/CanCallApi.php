<?php

namespace Cognesy\Instructor\ApiClient\Contracts;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Enums\Mode;
use Generator;
use GuzzleHttp\Promise\PromiseInterface;
use Saloon\Enums\Method;

interface CanCallApi
{
    public function request(array $body, string $endpoint, Method $method): static;

    public function get() : ApiResponse;

    /** @return Generator<PartialApiResponse>  */
    public function stream() : Generator;

    public function async() : PromiseInterface;

    public function defaultMaxTokens() : int;
    public function defaultModel() : string;

    public function getModeRequestClass(Mode $mode) : string;
}
