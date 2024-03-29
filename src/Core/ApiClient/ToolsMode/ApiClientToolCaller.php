<?php

namespace Cognesy\Instructor\Core\ApiClient\ToolsMode;

use Cognesy\Instructor\ApiClient\Contracts\CanCallTools;
use Cognesy\Instructor\Contracts\CanCallApiClient;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Utils\Result;

class ApiClientToolCaller implements CanCallApiClient
{
    public function __construct(
        private EventDispatcher $events,
        private CanCallTools $client,
    ) {}

    /**
     * Handle LLM function call
     */
    public function callApiClient(
        array $messages,
        ResponseModel $responseModel,
        string $model,
        array $options,
    ) : Result {
        $request = array_merge([
            'model' => $model,
            'messages' => $messages,
            'tools' => [$responseModel->toolCall],
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
