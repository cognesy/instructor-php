<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Mixin;

use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;

interface CanInfer
{
    public function infer(
        string|array        $messages = '',
        string|array|object $input = '',
        string|array|object $responseModel = [],
        string       $system = '',
        string       $prompt = '',
        array        $examples = [],
        string       $model = '',
        int          $maxRetries = 2,
        array        $options = [],
        OutputMode   $mode = OutputMode::Tools,
        ?string      $toolName = null,
        ?string      $toolDescription = null,
        string       $retryPrompt = '',
        ?LLMProvider $llm = null,
    ) : mixed;
}
