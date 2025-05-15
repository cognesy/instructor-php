<?php

namespace Cognesy\Instructor;

use Cognesy\Instructor\Features\Core\StructuredOutputResponse;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

/**
 * The Instructor class will be deprecated.
 * Use the StructuredOutput class instead.
 */
class Instructor extends StructuredOutput
{
    /**
     * This method is deprecated. Use `create()->get()` instead.
     */
    #[\Deprecated('Use create()->get() instead')]
    public function respond(
        string|array        $messages = '',
        string|array|object $input = '',
        string|array|object $responseModel = [],
        string              $system = '',
        string              $prompt = '',
        array               $examples = [],
        string              $model = '',
        int                 $maxRetries = 0,
        array               $options = [],
        string              $toolName = '',
        string              $toolDescription = '',
        string              $retryPrompt = '',
        OutputMode $mode = OutputMode::Tools
    ) : mixed {
        return $this->create(
            messages: $messages,
            input: $input,
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

    /**
     * This method is deprecated. Use `create()` instead.
     */
    #[\Deprecated('Use create() instead')]
    public function request(
        string|array        $messages = '',
        string|array|object $input = '',
        string|array|object $responseModel = [],
        string              $system = '',
        string              $prompt = '',
        array               $examples = [],
        string              $model = '',
        int                 $maxRetries = 0,
        array               $options = [],
        string              $toolName = '',
        string              $toolDescription = '',
        string              $retryPrompt = '',
        OutputMode          $mode = OutputMode::Tools,
    ) : StructuredOutputResponse {
        return $this->create(
            messages: $messages,
            input: $input,
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
        );
    }
}
