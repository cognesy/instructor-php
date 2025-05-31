<?php

namespace Cognesy\Polyglot\LLM\Contracts;

use Cognesy\Polyglot\LLM\InferenceRequest;

interface CanMapRequestBody
{
    public function toRequestBody(InferenceRequest $request): array;
}