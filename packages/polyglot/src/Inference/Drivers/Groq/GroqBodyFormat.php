<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Groq;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
class GroqBodyFormat extends OpenAICompatibleBodyFormat
{
    #[\Override]
    public function toRequestBody(InferenceRequest $request) : array {
        $requestBody = parent::toRequestBody($request);

        // Parent class already converts max_tokens to max_completion_tokens
        // No additional conversion needed for Groq

        return $requestBody;
    }

    // CAPABILITIES ///////////////////////////////////////////

    #[\Override]
    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }

    // INTERNAL ///////////////////////////////////////////////

    // Groq API supports json_object, json_schema and text -- exactly the base shapes, so there
    // is no response-format override here. There used to be one: it injected handlers that
    // rebuilt the base payloads verbatim, which the closure indirection made hard to see.
}
