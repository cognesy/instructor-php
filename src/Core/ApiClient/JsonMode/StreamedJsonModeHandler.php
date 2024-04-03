<?php
namespace Cognesy\Instructor\Core\ApiClient\JsonMode;

use Cognesy\Instructor\ApiClient\Contracts\CanCallJsonCompletion;
use Cognesy\Instructor\Core\ApiClient\AbstractStreamedCallHandler;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\Result;
use Exception;

class StreamedJsonModeHandler extends AbstractStreamedCallHandler
{
    private CanCallJsonCompletion $client;

    public function __construct(
        EventDispatcher $events,
        CanCallJsonCompletion $client,
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
            $stream = $this->client->jsonCompletion(
                messages: $this->request['messages'] ?? [],
                responseFormat: $this->request['response_format'] ?? [],
                model: $this->request['model'] ?? '',
                options: Arrays::unset($this->request, ['model', 'messages', 'response_format'])
            )->streamAll();
        } catch (Exception $e) {
            return Result::failure($e);
        }
        return Result::success($stream);
    }
}