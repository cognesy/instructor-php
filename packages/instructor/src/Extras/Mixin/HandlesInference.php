<?php declare(strict_types=1);
namespace Cognesy\Instructor\Extras\Mixin;

use Cognesy\Events\EventBusResolver;
use Cognesy\Instructor\Creation\StructuredOutputConfigBuilder;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\InferenceRuntime;
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
        ?string      $toolName = null,
        ?string      $toolDescription = null,
        string       $retryPrompt = '',
        ?LLMProvider $llm = null,
    ) : mixed {
        $provider = $llm ?? LLMProvider::new();
        $runtime = new StructuredOutputRuntime(
            inference: InferenceRuntime::fromProvider($provider),
            events: EventBusResolver::using(null),
            config: (new StructuredOutputConfigBuilder())
                ->with(
                    outputMode: $mode,
                    maxRetries: $maxRetries,
                    retryPrompt: $retryPrompt,
                    toolName: $toolName,
                    toolDescription: $toolDescription,
                )
                ->create(),
        );

        $request = new StructuredOutputRequest(
            messages: $messages,
            requestedSchema: $responseModel,
            system: $system,
            prompt: $prompt,
            examples: $examples,
            model: $model,
            options: $options,
        );

        return $runtime->create($request)->get();
    }
}
