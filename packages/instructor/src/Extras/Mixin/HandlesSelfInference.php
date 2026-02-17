<?php declare(strict_types=1);
namespace Cognesy\Instructor\Extras\Mixin;

use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Creation\StructuredOutputConfigBuilder;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;

/**
 * Handles self-inference for the class implementing this trait.
 *
 * This trait provides a static method `infer` that allows the class to perform inference
 * using the provided parameters. It utilizes runtime-first structured extraction to handle the
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
     * @param LLMProvider|null $llm
     * @param HttpClient|null $httpClient
     * @return static
     * @throws \Exception
     */
    public static function infer(
        string|array $messages = '',
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
        ?HttpClient $httpClient = null,
    ) : static {
        $configBuilder = (new StructuredOutputConfigBuilder())
            ->withMaxRetries($maxRetries)
            ->withOutputMode($mode)
            ->withRetryPrompt($retryPrompt);
        if ($toolName !== null) {
            $configBuilder = $configBuilder->withToolName($toolName);
        }
        if ($toolDescription !== null) {
            $configBuilder = $configBuilder->withToolDescription($toolDescription);
        }
        $request = new StructuredOutputRequest(
            messages: $messages,
            requestedSchema: self::class,
            system: $system,
            prompt: $prompt,
            examples: $examples,
            model: $model,
            options: $options,
        );
        return StructuredOutputRuntime::fromProvider(
            provider: $llm ?? LLMProvider::new(),
            httpClient: $httpClient,
            structuredConfig: $configBuilder->create(),
        )->create($request)->getInstanceOf(self::class);
    }
}
