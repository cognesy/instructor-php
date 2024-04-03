<?php
namespace Cognesy\Instructor\Core\ApiClient\ToolsMode;

use Cognesy\Instructor\ApiClient\Contracts\CanCallTools;
use Cognesy\Instructor\Core\ApiClient\AbstractStreamedCallHandler;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\Result;
use Exception;

class StreamedToolCallHandler extends AbstractStreamedCallHandler
{
    private CanCallTools $client;

    public function __construct(
        EventDispatcher $events,
        CanCallTools $client,
        array $request,
        ResponseModel $responseModel,
    ) {
        $this->client = $client;
        $this->events = $events;
        $this->request = $request;
        $this->responseModel = $responseModel;
    }

    protected function getStream() : Result {
        try {
            $stream = $this->client->toolsCall(
                messages: $this->request['messages'] ?? [],
                tools: [$this->responseModel->toolCall],
                toolChoice: [
                    'type' => 'function',
                    'function' => ['name' => $this->responseModel->functionName]
                ],
                model: $this->request['model'] ?? '',
                options: Arrays::unset($this->request, ['model', 'messages', 'tools', 'tool_choice'])
            )->stream();
        } catch (Exception $e) {
            return Result::failure($e);
        }
        return Result::success($stream);
    }
}
