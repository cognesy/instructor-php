<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenRouter;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIBodyFormat;

class OpenRouterBodyFormat extends OpenAIBodyFormat
{
    // CAPABILITIES /////////////////////////////////////////

    #[\Override]
    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }
}