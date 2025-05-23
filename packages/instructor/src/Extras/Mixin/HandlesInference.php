<?php
namespace Cognesy\Instructor\Extras\Mixin;

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\LLM;

trait HandlesInference {
    public function infer(
        string|array        $messages = '',
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
    ) : mixed {
        return (new StructuredOutput(
            llm: $llm ?? new LLM()
        ))->create(
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
        )->get();
    }
}
