<?php

namespace Cognesy\Polyglot\LLM\Drivers\OpenRouter;

use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIBodyFormat;
use Cognesy\Polyglot\LLM\InferenceRequest;

class OpenRouterBodyFormat extends OpenAIBodyFormat
{
    // CAPABILITIES /////////////////////////////////////////

    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }
}