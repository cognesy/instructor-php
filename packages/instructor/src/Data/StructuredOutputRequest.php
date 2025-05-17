<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Polyglot\LLM\Enums\OutputMode;

class StructuredOutputRequest
{
    use Traits\StructuredOutputRequest\HandlesMessages;
    use Traits\StructuredOutputRequest\HandlesRetries;
    use Traits\StructuredOutputRequest\HandlesSchema;

    protected StructuredOutputConfig $config;
    private StructuredOutputRequestInfo $requestInfo;
    private ChatTemplate $chatTemplate;

    public function __construct(
        string|array $messages,
        string|array|object $input,
        string|array|object $requestedSchema,
        ResponseModel $responseModel,
        string        $system,
        string        $prompt,
        array         $examples,
        string        $model,
        int           $maxRetries,
        array         $options,
        string        $toolName,
        string        $toolDescription,
        string        $retryPrompt,
        OutputMode    $mode,
        array         $cachedContext,
        StructuredOutputConfig $config,
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
        $this->requestedSchema = $requestedSchema;
        $this->responseModel = $responseModel;

        $this->config = $config;
        $this->toolName = $toolName ?: $this->config->toolName();
        $this->toolDescription = $toolDescription ?: $this->config->toolDescription();
        $this->chatTemplate = new ChatTemplate($this->config);
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
