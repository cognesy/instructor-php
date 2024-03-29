<?php

namespace Cognesy\Instructor\Core\ApiClient;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\LLM\RequestSentToLLM;
use Cognesy\Instructor\Events\LLM\RequestToLLMFailed;
use Cognesy\Instructor\Events\LLM\ResponseReceivedFromLLM;
use Cognesy\Instructor\Utils\Result;
use Exception;

abstract class AbstractCallHandler
{
    protected EventDispatcher $events;
    protected array $request;
    protected ResponseModel $responseModel;

    /**
     * Handle chat call
     * @return Result<ApiResponse, mixed>
     */
    public function handle() : Result {
        try {
            $this->events->dispatch(new RequestSentToLLM($this->request));
            $response = $this->getResponse();
            $this->events->dispatch(new ResponseReceivedFromLLM($response));
        } catch (Exception $e) {
            $event = new RequestToLLMFailed($this->request, $e->getMessage());
            $this->events->dispatch($event);
            return Result::failure($event);
        }
        if (empty($response->content)) {
            return Result::failure(new RequestToLLMFailed($this->request, 'Empty response content'));
        }
        return Result::success($response);
    }

    abstract protected function getResponse() : ApiResponse;
}