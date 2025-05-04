<?php

namespace Cognesy\Instructor\Extras\Mixin;

use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\LLM;

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
        OutputMode          $mode = OutputMode::Tools,
        string              $toolName = '',
        string              $toolDescription = '',
        string              $retryPrompt = '',
        ?LLM                $llm = null,
    ) : mixed;
}
