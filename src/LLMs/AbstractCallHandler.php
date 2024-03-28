<?php

namespace Cognesy\Instructor\LLMs;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Data\FunctionCall;
use Cognesy\Instructor\Data\LLMResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\LLM\RequestSentToLLM;
use Cognesy\Instructor\Events\LLM\RequestToLLMFailed;
use Cognesy\Instructor\Events\LLM\ResponseReceivedFromLLM;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\Result;
use Exception;

abstract class AbstractCallHandler
{
    protected EventDispatcher $events;
    protected array $request;
    protected ResponseModel $responseModel;

    /**
     * Handle chat call
     * @return Result<LLMResponse, mixed>
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
        // which functions have been called (or selected - if parallel tools on)
        $functionCalls = $this->getFunctionCalls($response);
        if (empty($functionCalls)) {
            return Result::failure(new RequestToLLMFailed($this->request, 'No tool calls found in the response'));
        }
        // handle finishReason other than 'stop'
        return Result::success(new LLMResponse(
            functionCalls: $functionCalls,
            finishReason: $response->finishReason,
            rawResponse: $response->responseData,
            isComplete: true
        ));
    }

    protected function getFunctionCalls(ApiResponse $response) : array {
        $jsonData = Json::find($response->content);
        if (empty($jsonData)) {
            return [];
        }
        $functionName = $response->functionName ?: $this->responseModel->functionName;
        return [new FunctionCall(
            id: $response->id ?? '',
            functionName: $functionName,
            functionArgsJson: $jsonData
        )];
//        $functionCalls = [];
//        foreach ($response->choices[0]->message->toolCalls as $data) {
//            $functionCalls[] = new FunctionCall(
//                toolCallId: $data->id ?? '',
//                functionName: $data->function->name ?? '',
//                functionArgsJson: $data->function->arguments ?? ''
//            );
//        }
//        return $functionCalls;
    }

    abstract protected function getResponse() : ApiResponse;
}