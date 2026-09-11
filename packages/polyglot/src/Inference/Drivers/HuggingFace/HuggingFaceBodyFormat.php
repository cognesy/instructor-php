<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\HuggingFace;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;

class HuggingFaceBodyFormat extends OpenAICompatibleBodyFormat
{
    // CAPABILITIES /////////////////////////////////////////

    #[\Override]
    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }
}
