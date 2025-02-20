<?php

namespace Cognesy\Instructor\Extras\Mixin;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\LLM\LLM\LLM;

interface CanInfer
{
    public function infer(
        string|array        $messages = '',
        string|array|object $input = '',
        string|array|object $responseModel = [],
        string              $system = '',
        string              $prompt = '',
        array               $examples = [],
        string              $model = '',
        int                 $maxRetries = 2,
        array               $options = [],
        Mode                $mode = Mode::Tools,
        string              $toolName = '',
        string              $toolDescription = '',
        string              $retryPrompt = '',
        LLM                 $llm = null,
    ) : mixed;
}
