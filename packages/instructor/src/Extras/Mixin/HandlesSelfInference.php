<?php
namespace Cognesy\Instructor\Extras\Mixin;

use Cognesy\Instructor\Instructor;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\LLM;

trait HandlesSelfInference {
    public static function infer(
        string|array        $messages = '',
        string|array|object $input = '',
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
    ) : static {
        return (new Instructor(
            llm: $llm ?? new LLM()
        ))->respond(
            messages: $messages,
            input: $input,
            responseModel: self::class,
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
        );
    }
}
