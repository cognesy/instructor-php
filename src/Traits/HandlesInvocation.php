<?php
namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\Features\Core\Data\Request;
use Cognesy\Instructor\Features\Core\Data\ResponseModel;
use Cognesy\Instructor\Features\Core\InstructorResponse;
use Cognesy\Instructor\Features\Core\PartialsGenerator;
use Cognesy\Instructor\Features\Core\RequestHandler;
use Cognesy\Instructor\Features\Core\ResponseGenerator;
use Cognesy\Instructor\Features\Core\ResponseModelFactory;
use Cognesy\Instructor\Features\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Features\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Features\Schema\Utils\ReferenceQueue;
use Cognesy\Instructor\Utils\Settings;
use Exception;

trait HandlesInvocation
{
    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
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
        Mode                $mode = Mode::Tools
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
     * Creates the request to be executed
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
        Mode                $mode = Mode::Tools,
    ) : InstructorResponse {
        $this->queueEvent(new RequestReceived());
        $this->dispatchQueuedEvents();

        if (empty($responseModel)) {
            throw new Exception('Response model cannot be empty. Provide a class name, instance, or schema array.');
        }

        $requestedSchema = $responseModel;
        $responseModel = $this->makeResponseModel($requestedSchema, $toolName, $toolDescription);

        $request = new Request(
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
            mode: $mode ?? Mode::Tools,
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

        return new InstructorResponse(
            request: $request,
            requestHandler: $requestHandler,
            events: $this->events,
        );
    }

    // INTERNAL /////////////////////////////////////////////////

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
