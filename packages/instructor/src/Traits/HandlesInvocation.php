<?php
namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputRequestReceived;
use Cognesy\Instructor\PendingStructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;
use Exception;

/**
 * Trait provides invocation handling functionality for the StructuredOutput class.
 */
trait HandlesInvocation
{
    /**
     * Processes the provided request information and creates a new request to be executed.
     *
     * @param StructuredOutputRequest $request The RequestInfo object containing all necessary data
     * for generating the request.
     */
    public function withRequest(StructuredOutputRequest $request) : static {
        $this->requestBuilder->withRequest($request);
        return $this;
    }

    /**
     * Sets values for the request builder and configures the StructuredOutput instance
     *
     * @param string|array $messages Text or chat sequence to be used for generating the response.
     * @param string|array|object $responseModel The class, JSON schema, or object representing the response format.
     * @param string $system The system instructions (optional).
     * @param string $prompt The prompt to guide the request's response generation (optional).
     * @param array $examples Example data to provide additional context for the request (optional).
     * @param string $model Specifies the model to be employed - check LLM documentation for more details.
     * @param int $maxRetries The maximum number of retries for the request in case of failure.
     * @param array $options Additional LLM options - check LLM documentation for more details.
     * @param string $toolName The name of the tool to be used in OutputMode::Tools.
     * @param string $toolDescription A description of the tool to be used in OutputMode::Tools.
     * @param string $retryPrompt The prompt to be used during retries.
     * @param OutputMode $mode The mode of operation for the request.
     * @throws Exception If the response model is empty or invalid.
     */
    public function with(
        string|array|Message|Messages|null $messages = null,
        string|array|object|null           $responseModel = null,
        ?string                        $system = null,
        ?string                        $prompt = null,
        ?array                         $examples = null,
        ?string                        $model = null,
        ?int                           $maxRetries = null,
        ?array                         $options = null,
        ?string                        $toolName = null,
        ?string                        $toolDescription = null,
        ?string                        $retryPrompt = null,
        ?OutputMode                    $mode = null,
    ) : static {
        $this->requestBuilder->with(
            messages: $messages,
            requestedSchema: $responseModel,
            system: $system,
            prompt: $prompt,
            examples: $examples,
            model: $model,
            options: $options,
        );
        $this->configBuilder->with(
            outputMode: $mode,
            maxRetries: $maxRetries,
            retryPrompt: $retryPrompt,
            toolName: $toolName,
            toolDescription: $toolDescription,
        );
        return $this;
    }

    /**
     * Creates a new StructuredOutputResponse instance based on the current request builder and configuration.
     *
     * This method initializes the request factory, request handler, and response generator,
     * and returns a StructuredOutputResponse object that can be used to handle the request.
     *
     * @return PendingStructuredOutput A response object providing access to various results retrieval methods.
     */
    public function create() : PendingStructuredOutput {
        $this->events->dispatch(new StructuredOutputRequestReceived());

        $config = $this->configBuilder->create();
        $request = $this->requestBuilder->createWith(
            config: $config,
            events: $this->events,
        );

        return new PendingStructuredOutput(
            request: $request,
            responseDeserializer: $this->responseDeserializer,
            responseValidator: $this->responseValidator,
            responseTransformer: $this->responseTransformer,
            llmProvider: $this->llmProvider,
            config: $config,
            events: $this->events,
        );
    }
}
