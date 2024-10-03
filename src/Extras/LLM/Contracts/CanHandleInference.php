<?php

namespace Cognesy\Instructor\Extras\LLM\Contracts;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\LLM\Data\LLMApiResponse;
use Cognesy\Instructor\Extras\LLM\Data\PartialLLMApiResponse;
use Cognesy\Instructor\Extras\LLM\InferenceRequest;
use Psr\Http\Message\ResponseInterface;

interface CanHandleInference
{
    public function handle(InferenceRequest $request) : ResponseInterface;
    public function getEndpointUrl(InferenceRequest $request) : string;
    public function getRequestHeaders() : array;
    public function getRequestBody(
        array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = '',
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text,
    ) : array;
    public function toApiResponse(array $data): ?LLMApiResponse;
    public function toPartialApiResponse(array $data) : ?PartialLLMApiResponse;
    public function getData(string $data): string|bool;
}