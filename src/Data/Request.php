<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Core\Factories\ResponseModelFactory;
use Cognesy\Instructor\Enums\Mode;

class Request
{
    use Traits\Request\HandlesExamples;
    use Traits\Request\HandlesLLMClient;
    use Traits\Request\HandlesMessages;
    use Traits\Request\HandlesPrompts;
    use Traits\Request\HandlesRetries;
    use Traits\Request\HandlesSchema;

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
        ResponseModelFactory $responseModelFactory,
    ) {
        $this->responseModelFactory = $responseModelFactory;

        $this->cachedContext = $cachedContext;
        $this->connection = $connection;
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
            $this->responseModel = $this->responseModelFactory->fromAny(
                $this->requestedSchema(),
                $this->toolName(),
                $this->toolDescription()
            );
        }
    }

    public function copy(array $messages) : self {
        return (clone $this)->withMessages($messages);
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
}
