<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Core\ResponseModelFactory;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Extras\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Schema\Utils\ReferenceQueue;
use Cognesy\Instructor\Utils\Settings;

class Request
{
    use Traits\Request\HandlesLLMClient;
    use Traits\Request\HandlesMessages;
    use Traits\Request\HandlesRetries;
    use Traits\Request\HandlesSchema;

    private EventDispatcher $events;

    public function __construct(
        string|array $messages,
        string|array|object $input,
        string|array|object $responseModel,
        string $system,
        string $prompt,
        array $examples,
        string $model,
        int $maxRetries,
        array $options,
        string $toolName,
        string $toolDescription,
        string $retryPrompt,
        Mode $mode,
        array $cachedContext,
        string $connection,
        ?CanHandleHttp $httpClient,
        ?CanHandleInference $driver,
        EventDispatcher $events,
    ) {
        $this->events = $events;

        $this->httpClient = $httpClient;
        $this->driver = $driver;
        $this->connection = $connection;

        $this->cachedContext = $cachedContext;
        $this->options = $options;
        $this->maxRetries = $maxRetries;
        $this->mode = $mode;
        $this->input = $input;
        $this->prompt = $prompt;
        $this->retryPrompt = $retryPrompt;
        $this->examples = $examples;
        $this->system = $system;
        $this->model = $model;

        $this->messages = $this->normalizeMessages($messages);
        $this->toolName = $toolName ?: $this->defaultToolName;
        $this->toolDescription = $toolDescription ?: $this->defaultToolDescription;

        $this->requestedSchema = $responseModel;
        if (!empty($this->requestedSchema)) {
            $this->responseModel = $this->makeResponseModel();
        }
    }

    public function toArray() : array {
        return [
            'messages' => $this->messages,
            'input' => $this->input,
            'responseModel' => $this->responseModel,
            'system' => $this->system,
            'prompt' => $this->prompt,
            'examples' => $this->examples,
            'model' => $this->model,
            'maxRetries' => $this->maxRetries,
            'options' => $this->options,
            'toolName' => $this->toolName(),
            'toolDescription' => $this->toolDescription(),
            'retryPrompt' => $this->retryPrompt,
            'mode' => $this->mode(),
            'cachedContext' => $this->cachedContext,
            'connection' => $this->connection,
        ];
    }

    private function makeResponseModel() : ResponseModel {
        $schemaFactory = new SchemaFactory(
            Settings::get('llm', 'useObjectReferences', false)
        );
        $responseModelFactory = new ResponseModelFactory(
            new ToolCallBuilder($schemaFactory, new ReferenceQueue()),
            $schemaFactory,
            $this->events
        );
        return $responseModelFactory->fromAny(
            $this->requestedSchema(),
            $this->toolName(),
            $this->toolDescription()
        );
    }
}
