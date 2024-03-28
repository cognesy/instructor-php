<?php

namespace Cognesy\Instructor\LLMs\ApiClient\ToolsMode;

use Cognesy\Instructor\ApiClient\Contracts\CanCallTools;
use Cognesy\Instructor\Contracts\CanCallFunction;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Utils\Result;

class ApiClientToolCaller implements CanCallFunction
{
    public function __construct(
        private EventDispatcher $events,
        private CanCallTools $client,
    ) {}

    /**
     * Handle LLM function call
     */
    public function callFunction(
        array $messages,
        ResponseModel $responseModel,
        string $model,
        array $options,
    ) : Result {
        $request = array_merge([
            'model' => $model,
            'messages' => $messages,
            'tools' => [$responseModel->functionCall],
            'tool_choice' => [
                'type' => 'function',
                'function' => ['name' => $responseModel->functionName]
            ]
        ], $options);

        return match($options['stream'] ?? false) {
            true => (new StreamedToolCallHandler($this->events, $this->client, $request, $responseModel))->handle(),
            default => (new ToolCallHandler($this->events, $this->client, $request, $responseModel))->handle()
        };
    }
}
