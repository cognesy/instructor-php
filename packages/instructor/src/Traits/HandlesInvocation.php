<?php
namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputConfig;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Data\StructuredOutputRequestInfo;
use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\Features\Core\PartialsGenerator;
use Cognesy\Instructor\Features\Core\RequestHandler;
use Cognesy\Instructor\Features\Core\ResponseGenerator;
use Cognesy\Instructor\Features\Core\ResponseModelFactory;
use Cognesy\Instructor\Features\Core\StructuredOutputResponse;
use Cognesy\Instructor\Features\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Features\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Features\Schema\Utils\ReferenceQueue;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Events\EventDispatcher;
use Exception;

/**
 * Trait provides invocation handling functionality for the StructuredOutput class.
 */
trait HandlesInvocation
{
    /**
     * Processes the provided request information and creates a new request to be executed.
     *
     * @param StructuredOutputRequestInfo $request The RequestInfo object containing all necessary data
     * for generating the request.
     *
     * @return StructuredOutputResponse The response generated based on the provided request details.
     */
    public function withRequest(StructuredOutputRequestInfo $request) : StructuredOutputResponse {
        return $this->request(
            messages: $request->messages ?? [],
            input: $request->input ?? [],
            responseModel: $request->responseModel ?? [],
            system: $request->system ?? '',
            prompt: $request->prompt ?? '',
            examples: $request->examples ?? [],
            model: $request->model ?? '',
            maxRetries: $request->maxRetries ?? 0,
            options: $request->options ?? [],
            toolName: $request->toolName ?? '',
            toolDescription: $request->toolDescription ?? '',
            retryPrompt: $request->retryPrompt ?? '',
            mode: $request->mode ?? OutputMode::Tools,
        );
    }

    /**
     * Processes a request using provided input, system configurations, and response specifications.
     *
     * @param string|array $messages Text or chat sequence to be used for generating the response.
     * @param string|array|object $input Data or input to send with the request (optional).
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
    public function create(
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
        $this->queueEvent(new RequestReceived());
        $this->dispatchQueuedEvents();

        if (empty($responseModel)) {
            throw new Exception('Response model cannot be empty. Provide a class name, instance, or schema array.');
        }

        $requestedSchema = $responseModel;
        $responseModel = $this->makeResponseModel(
            $requestedSchema,
            new StructuredOutputConfig(
                retryPrompt: $this->config->retryPrompt(),
                modePrompts: $this->config->modePrompts(),
                chatStructure: $this->config->chatStructure(),
                toolName: $toolName ?: $this->config->toolName(),
                toolDescription: $toolDescription ?: $this->config->toolDescription(),
                useObjectReferences: $this->config->useObjectReferences()
            ),
            $this->events,
        );

        $request = new StructuredOutputRequest(
            messages: $messages ?? [],
            input: $input ?? [],
            requestedSchema: $requestedSchema ?? [],
            responseModel: $responseModel,
            system: $system ?? '',
            prompt: $prompt ?? '',
            examples: $examples ?? [],
            model: $model ?? '',
            maxRetries: $maxRetries ?? 0,
            options: $options ?? [],
            toolName: $toolName ?? '',
            toolDescription: $toolDescription ?? '',
            retryPrompt: $retryPrompt ?? '',
            mode: $mode ?? OutputMode::Tools,
            cachedContext: $this->cachedContext ?? [],
            config: $this->config,
        );

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
            llm: $this->llm,
            events: $this->events,
        );

        return new StructuredOutputResponse(
            request: $request,
            requestHandler: $requestHandler,
            events: $this->events,
        );
    }

    /**
     * Processes a request using provided input, system configurations, and response specifications
     * and returns the result directly.
     *
     * @param string|array $messages Text or chat sequence to be used for generating the response.
     * @param string|array|object $input Data or input to send with the request (optional).
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
     * Creates a ResponseModel instance utilising the provided schema, tool name, and description.
     *
     * @param string|array|object $requestedSchema The schema to be used for creating the response model, provided as a string, array, or object.
     * @param string $toolName The name of the tool, which can be overridden by default settings if not provided.
     * @param string $toolDescription The description of the tool, which can be overridden by default settings if not provided.
     * @param bool $useObjectReferences Indicates whether to use object references in the schema.
     * @param EventDispatcher|null $events An event dispatcher for handling events during the response object creation process.
     *
     * @return ResponseModel Returns a ResponseModel object constructed using the requested schema, tool name, and description.
     */
    private function makeResponseModel(
        string|array|object $requestedSchema,
        StructuredOutputConfig $config,
        EventDispatcher $events,
    ) : ResponseModel {
        $schemaFactory = new SchemaFactory($config->useObjectReferences());

        $responseModelFactory = new ResponseModelFactory(
            new ToolCallBuilder($schemaFactory, new ReferenceQueue()),
            $schemaFactory,
            $events,
        );

        return $responseModelFactory->fromAny(
            $requestedSchema,
            $config->toolName(),
            $config->toolDescription()
        );
    }
}
