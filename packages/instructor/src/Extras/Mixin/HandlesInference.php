<?php
namespace Cognesy\Instructor\Extras\Mixin;

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;

trait HandlesInference {
    public function infer(
        string|array        $messages = '',
        string|array|object $responseModel = [],
        string       $system = '',
        string       $prompt = '',
        array        $examples = [],
        string       $model = '',
        int          $maxRetries = 2,
        array        $options = [],
        OutputMode   $mode = OutputMode::Tools,
        string       $toolName = '',
        string       $toolDescription = '',
        string       $retryPrompt = '',
        ?LLMProvider $llm = null,
    ) : mixed {
        return (new StructuredOutput())
            ->withLLMProvider($llm ?? LLMProvider::new())
            ->with(
                messages: $messages,
                responseModel: $responseModel,
                system: $system,
                prompt: $prompt,
                examples: $examples,
                model: $model,
                maxRetries: $maxRetries,
                options: $options,
                toolName: $toolName,
                toolDescription: $toolDescription,
                retryPrompt: $retryPrompt,
                mode: $mode,
            )
            ->get();
    }
}
