<?php
namespace Cognesy\Instructor\Data;

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
        array         $options,
        array         $cachedContext,
        StructuredOutputConfig $config,
    ) {
        $this->cachedContext = $cachedContext;
        $this->options = $options;
        $this->input = $input;
        $this->prompt = $prompt;
        $this->examples = $examples;
        $this->system = $system;
        $this->model = $model;

        $this->messages = $this->normalizeMessages($messages);
        $this->requestedSchema = $requestedSchema;
        $this->responseModel = $responseModel;

        $this->config = $config;
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
            'options' => $this->options,
            'mode' => $this->mode(),
            'cachedContext' => $this->cachedContext,
            'config' => $this->config->toArray(),
        ];
    }

    public function isStreamed() : bool {
        return $this->options['stream'] ?? false;
    }
}
