<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\HuggingFace;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIRequestAdapter;

class HuggingFaceRequestAdapterAdapter extends OpenAIRequestAdapter
{
    // HuggingFace router uses standard OpenAI-compatible API
    // No URL customization needed - provider is specified in the model name
}