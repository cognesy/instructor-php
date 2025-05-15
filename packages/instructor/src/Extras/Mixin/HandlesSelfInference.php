<?php
namespace Cognesy\Instructor\Extras\Mixin;

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\LLM;

/**
 * Handles self-inference for the class implementing this trait.
 *
 * This trait provides a static method `infer` that allows the class to perform inference
 * using the provided parameters. It utilizes the `StructuredOutput` class to handle the
 * inference process.
 */
trait HandlesSelfInference {
    /**
     * Performs inference on the class using the provided parameters.
     *
     * @param string|array $messages
     * @param string|array|object $input
     * @param string $system
     * @param string $prompt
     * @param array $examples
     * @param string $model
     * @param int $maxRetries
     * @param array $options
     * @param OutputMode $mode
     * @param string $toolName
     * @param string $toolDescription
     * @param string $retryPrompt
     * @param LLM|null $llm
     * @return static
     * @throws \Exception
     */
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
        return (new StructuredOutput(
            llm: $llm ?? new LLM()
        ))->create(
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
        )->getInstanceOf(self::class);
    }
}
