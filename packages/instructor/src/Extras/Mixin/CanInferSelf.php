<?php
namespace Cognesy\Instructor\Extras\Mixin;

use Cognesy\Polyglot\LLM\Enums\Mode;
use Cognesy\Polyglot\LLM\LLM;

interface CanInferSelf
{
    static public function infer(
        string|array        $messages = '',
        string|array|object $input = '',
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
    ) : static;
}
