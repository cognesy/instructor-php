<?php
namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Core\PartialsGenerator;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Core\StructuredOutputResponse;
use Cognesy\Instructor\Core\StructuredOutputStream;
use Cognesy\Instructor\Data\ChatTemplate;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Events\StructuredOutput\RequestReceived;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
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
     *
     * @return StructuredOutputResponse The response generated based on the provided request details.
     */
    public function withRequest(StructuredOutputRequest $request) : StructuredOutputResponse {
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
     * @return StructuredOutputResponse A response object providing access to various results retrieval methods.
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

        $this->requestBuilder->with(
            messages: $messages,
            requestedSchema: $responseModel,
            system: $system,
            prompt: $prompt,
            examples: $examples,
            model: $model,
            options: $options,
            config: $this->config,
        );

        return $this;
    }

    /**
     * Processes a request using provided input, system configurations, and response specifications
     * and returns the result directly.
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
     * @return mixed A result of processing the request transformed to the target value
     * @throws Exception If the response model is empty or invalid.
     */
    public function generate(
        string|array        $messages = '',
        string|array|object $responseModel = [],
        string              $system = '',
        string              $prompt = '',
        array               $examples = [],
        string              $model = '',
        int                 $maxRetries = -1,
        array               $options = [],
        string              $toolName = '',
        string              $toolDescription = '',
        string              $retryPrompt = '',
        ?OutputMode         $mode = null,
    ) : mixed {
        return $this->with(
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

        $request = $this->requestBuilder
            ->withConfig($this->config)
            ->build();

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
            requestMaterializer: new ChatTemplate($this->config),
            llm: $this->llm,
            events: $this->events,
        );

        return new StructuredOutputResponse(
            request: $request,
            requestHandler: $requestHandler,
            events: $this->events,
        );
    }

    public function response() : LLMResponse {
        return $this->create()->response();
    }

    /**
     * Processes a request using provided input, system configurations,
     * and response specifications and returns the result directly.
     *
     * @return mixed A result of processing the request transformed to the target value
     */
    public function get() : mixed {
        return $this->create()->get();
    }

    public function getString() : string {
        return $this->create()->getString();
    }

    public function getFloat() : float {
        return $this->create()->getFloat();
    }

    public function getInt() : int {
        return $this->create()->getInt();
    }

    public function getBoolean() : bool {
        return $this->create()->getBoolean();
    }

    public function getObject() : object {
        return $this->create()->getObject();
    }

    public function getArray() : array {
        return $this->create()->getArray();
    }

    /**
     * Processes a request using provided input, system configurations,
     * and response specifications and returns a streamed result object.
     *
     * @return StructuredOutputStream A stream of the response
     */
    public function stream() : StructuredOutputStream {
        // turn on streaming mode
        $this->requestBuilder->withStreaming();
        return $this->create()->stream();
    }
}
