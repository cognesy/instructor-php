<?php
namespace Cognesy\Instructor\Core\ApiClient\MdJsonMode;

use Cognesy\Instructor\ApiClient\Contracts\CanCallChatCompletion;
use Cognesy\Instructor\Core\ApiClient\AbstractStreamedCallHandler;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\Result;
use Exception;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class StreamedMdJsonModeHandler extends AbstractStreamedCallHandler
{
    private CanCallChatCompletion $client;

    public function __construct(
        EventDispatcher $events,
        CanCallChatCompletion $client,
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
            $stream = $this->client->chatCompletion(
                messages: $this->request['messages'] ?? [],
                model: $this->request['model'] ?? '',
                options: Arrays::unset($this->request, ['model', 'messages'])
            )->stream();
        } catch (Exception $e) {
            return Result::failure($e);
        }
        return Result::success($stream);
    }
}