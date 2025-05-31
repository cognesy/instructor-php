<?php

namespace Cognesy\Polyglot\LLM\Contracts;

use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\LLM\InferenceRequest;

interface ProviderRequestAdapter
{
    public function toHttpClientRequest(InferenceRequest $request) : HttpClientRequest;
}
