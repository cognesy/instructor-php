<?php
namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Core\PartialsGenerator;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Core\StructuredOutputResponse;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Events\StructuredOutput\RequestReceived;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
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
        return $this->with(
            messages: $request->messages() ?? [],
            responseModel: $request->responseModel() ?? [],
            system: $request->system() ?? '',
            prompt: $request->prompt() ?? '',
            examples: $request->examples() ?? [],
            model: $request->model() ?? '',
            maxRetries: $request->config()?->maxRetries() ?? -1,
            options: $request->options() ?? [],
            toolName: $request->config()?->toolName() ?? '',
            toolDescription: $request->config()?->toolDescription() ?? '',
            retryPrompt: $request->config()?->retryPrompt() ?? '',
            mode: $request->config()?->outputMode() ?? OutputMode::Tools,
        );
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
        string|array|Message|Messages $messages = '',
        string|array|object           $responseModel = [],
        string                        $system = '',
        string                        $prompt = '',
        array                         $examples = [],
        string                        $model = '',
        int                           $maxRetries = -1,
        array                         $options = [],
        string                        $toolName = '',
        string                        $toolDescription = '',
        string                        $retryPrompt = '',
        ?OutputMode                   $mode = null,
    ) : static {
        $this->config->withOverrides(
            outputMode: $mode ?: $this->config->outputMode() ?: OutputMode::Tools,
            maxRetries: ($maxRetries >= 0) ? $maxRetries : $this->config->maxRetries(),
            retryPrompt: $retryPrompt ?: $this->config->retryPrompt(),
            toolName: $toolName ?: $this->config->toolName(),
            toolDescription: $toolDescription ?: $this->config->toolDescription(),
        );

        $this->messages = match (true) {
            empty($messages) => $this->messages,
            default => Messages::fromAny($messages),
        };
        $this->requestedSchema = $responseModel ?: $this->requestedSchema;
        $this->system = $system ?: $this->system;
        $this->prompt = $prompt ?: $this->prompt;
        $this->examples = $examples ?: $this->examples;
        $this->model = $model ?: $this->model;
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * Creates a new StructuredOutputResponse instance based on the current request builder and configuration.
     *
     * This method initializes the request factory, request handler, and response generator,
     * and returns a StructuredOutputResponse object that can be used to handle the request.
     *
     * @return StructuredOutputResponse A response object providing access to various results retrieval methods.
     */
    public function create() : StructuredOutputResponse {
        $this->queueEvent(new RequestReceived());
        $this->dispatchQueuedEvents();

        $request = $this->build();

        $requestHandler = new RequestHandler(
            request: $request,
            responseGenerator: new ResponseGenerator(
                $this->responseDeserializer,
                $this->responseValidator,
                $this->responseTransformer,
                $this->events,
            ),
            partialsGenerator: new PartialsGenerator(
                $this->responseDeserializer,
                $this->responseTransformer,
                $this->events,
            ),
            requestMaterializer: new RequestMaterializer($this->config),
            llm: $this->llm(),
            events: $this->events,
        );

        return new StructuredOutputResponse(
            request: $request,
            requestHandler: $requestHandler,
            events: $this->events,
        );
    }
}
