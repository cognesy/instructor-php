<?php

namespace Cognesy\Instructor\ApiClient\Contracts;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Generator;
use GuzzleHttp\Promise\PromiseInterface;
use Saloon\Http\Response;

interface CanCallApi
{
    public function respondRaw(): Response;
    public function streamRaw(): Generator;
    public function asyncRaw(callable $onSuccess, callable $onError) : PromiseInterface;
    public function respond() : ApiResponse;
    public function stream() : Generator;
    public function streamAll() : array;
}