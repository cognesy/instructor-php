<?php
namespace Cognesy\Instructor\Features\Core\Data;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Utils\Settings;

class StructuredOutputRequest
{
    use Traits\Request\HandlesMessages;
    use Traits\Request\HandlesRetries;
    use Traits\Request\HandlesSchema;

    public function __construct(
        string|array $messages,
        string|array|object $input,
        string|array|object $requestedSchema,
        ResponseModel $responseModel,
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
    ) {
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
        $this->toolName = $toolName ?: Settings::get('llm', 'defaultToolName');
        $this->toolDescription = $toolDescription ?: Settings::get('llm', 'defaultToolDescription');

        $this->requestedSchema = $requestedSchema;
        $this->responseModel = $responseModel;
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
        ];
    }

    public function isStreamed() : bool {
        return $this->options['stream'] ?? false;
    }
}
