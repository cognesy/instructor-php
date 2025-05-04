<?php
namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\Features\Core\Data\ResponseModel;
use Cognesy\Instructor\Features\Core\Data\StructuredOutputRequest;
use Cognesy\Instructor\Features\Core\Data\StructuredOutputRequestInfo;
use Cognesy\Instructor\Features\Core\PartialsGenerator;
use Cognesy\Instructor\Features\Core\RequestHandler;
use Cognesy\Instructor\Features\Core\ResponseGenerator;
use Cognesy\Instructor\Features\Core\ResponseModelFactory;
use Cognesy\Instructor\Features\Core\StructuredOutputResponse;
use Cognesy\Instructor\Features\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Features\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Features\Schema\Utils\ReferenceQueue;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Settings;
use Exception;

/**
 * Trait provides invocation handling functionality for the Instructor class.
 */
trait HandlesInvocation
{
    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
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
     * @param string $toolName The name of the tool to be used in Mode::Tools.
     * @param string $toolDescription A description of the tool to be used in Mode::Tools.
     * @param string $retryPrompt The prompt to be used during retries.
     * @param OutputMode $mode The mode of operation for the request.
     * @return StructuredOutputResponse A response object providing access to various results retrieval methods.
     * @throws Exception If the response model is empty or invalid.
     */
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
        return $this->request(
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
     * Processes the provided request information and creates a new request to be executed.
     *
     * @param StructuredOutputRequestInfo $request The RequestInfo object containing all necessary data
     *                             for generating the request.
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
     * @param string $toolName The name of the tool to be used in Mode::Tools.
     * @param string $toolDescription A description of the tool to be used in Mode::Tools.
     * @param string $retryPrompt The prompt to be used during retries.
     * @param OutputMode $mode The mode of operation for the request.
     * @return StructuredOutputResponse A response object providing access to various results retrieval methods.
     * @throws Exception If the response model is empty or invalid.
     */
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
        $this->queueEvent(new RequestReceived());
        $this->dispatchQueuedEvents();

        if (empty($responseModel)) {
            throw new Exception('Response model cannot be empty. Provide a class name, instance, or schema array.');
        }

        $requestedSchema = $responseModel;
        $responseModel = $this->makeResponseModel($requestedSchema, $toolName, $toolDescription);

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

    // INTERNAL /////////////////////////////////////////////////

    /**
     * Creates a ResponseModel instance utilizing the provided schema, tool name, and description.
     *
     * @param string|array|object $requestedSchema The schema to be used for creating the response model, provided as a string, array, or object.
     * @param string $toolName The name of the tool, which can be overridden by default settings if not provided.
     * @param string $toolDescription The description of the tool, which can be overridden by default settings if not provided.
     *
     * @return ResponseModel Returns a ResponseModel object constructed using the requested schema, tool name, and description.
     */
    private function makeResponseModel(
        string|array|object $requestedSchema,
        string $toolName,
        string $toolDescription,
    ) : ResponseModel {
        $toolName = $toolName ?: Settings::get('llm', 'defaultToolName', 'extracted_data');
        $toolDescription = $toolDescription ?: Settings::get('llm', 'defaultToolDescription', 'Function call based on user instructions.');
        $schemaFactory = new SchemaFactory(
            Settings::get('llm', 'useObjectReferences', false)
        );
        $responseModelFactory = new ResponseModelFactory(
            new ToolCallBuilder($schemaFactory, new ReferenceQueue()),
            $schemaFactory,
            $this->events
        );
        return $responseModelFactory->fromAny($requestedSchema, $toolName, $toolDescription);
    }
}
