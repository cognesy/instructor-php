<?php

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

interface ProviderRequestAdapter
{
    public function toHttpClientRequest(InferenceRequest $request) : HttpClientRequest;
}
