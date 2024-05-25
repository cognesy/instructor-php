<?php

namespace Cognesy\Instructor\ApiClient\Contracts;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Enums\Mode;
use Generator;
use GuzzleHttp\Promise\PromiseInterface;

interface CanCallApi
{
    public function request(array $messages, array $tools = [], array $toolChoice = [], array $responseFormat = [], string $model = '', array $options = []): static;

    public function chatCompletion(array $messages, string $model = '', array $options = []): static;

    public function jsonCompletion(array $messages, array $responseFormat, string $model = '', array $options = []): static;

    public function toolsCall(array $messages, array $tools, array $toolChoice, string $model = '', array $options = []): static;

    public function get() : ApiResponse;

    /** @return Generator<PartialApiResponse>  */
    public function stream() : Generator;

    public function async() : PromiseInterface;

    public function defaultModel() : string;

    public function getModeRequestClass(Mode $mode) : string;
}
