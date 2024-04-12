<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Contracts\CanHandlePartialResponse;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleResponse;
use Cognesy\Instructor\Core\ApiClient\ToolCallerFactory;
use Cognesy\Instructor\Core\ResponseModel\ResponseModelFactory;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\LLM\PartialJsonReceived;
use Cognesy\Instructor\Events\LLM\StreamedToolCallCompleted;
use Cognesy\Instructor\Events\RequestHandler\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\RequestHandler\ResponseGenerationFailed;
use Cognesy\Instructor\Events\RequestHandler\ResponseModelBuilt;
use Cognesy\Instructor\Events\RequestHandler\ToolCallRequested;
use Cognesy\Instructor\Events\RequestHandler\ToolCallResponseReceived;
use Cognesy\Instructor\Events\RequestHandler\ValidationRecoveryLimitReached;
use Cognesy\Instructor\Utils\Result;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class RequestHandler implements CanHandleRequest
{
    public function __construct(
        private ToolCallerFactory $toolCallerFactory,
        private ResponseModelFactory $responseModelFactory,
        private EventDispatcher $events,
        private CanHandleResponse $responseHandler,
        private CanHandlePartialResponse $partialResponseHandler,
    ) {}

    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
     */
    public function respondTo(Request $request) : Result {
        $requestedModel = $this->responseModelFactory->fromRequest($request);
        if ($request->options['stream'] ?? false) {
            $this->registerStreamListeners($requestedModel);
        }
        $this->events->dispatch(new ResponseModelBuilt($requestedModel));
        return $this->tryRespond($request, $requestedModel);
    }

    /**
     * Executes LLM call loop with validation until success or max retries reached
     */
    protected function tryRespond(Request $request, ResponseModel $responseModel) : Result {
        $retries = 0;
        $messages = $request->messages();
        while ($retries <= $request->maxRetries) {
            // get function caller instance
            $clientCaller = $this->toolCallerFactory->fromRequest($request);
            $this->events->dispatch(new ToolCallRequested($messages, $responseModel, $request));

            // run LLM inference
            $apiCallResult = $clientCaller->callApiClient(
                $messages,
                $responseModel,
                $request->model,
                $request->options
            );
            if ($apiCallResult->isFailure()) {
                return $apiCallResult;
            }

            /** @var ApiResponse $response */
            $response = $apiCallResult->unwrap();
            $this->events->dispatch(new ToolCallResponseReceived($response));

            $processingResult = $this->responseHandler->handleResponse($response, $responseModel);
            if ($processingResult->isSuccess()) {
                return $processingResult;
            }
            $errors = $processingResult->error();

            // retry if validation failed
            $this->partialResponseHandler->resetPartialResponse();
            $messages = $this->makeRetryMessages($messages, $responseModel, $response->content, $errors);
            $retries++;
            if ($retries <= $request->maxRetries) {
                $this->events->dispatch(new NewValidationRecoveryAttempt($retries, $errors));
            }
        }
        $this->events->dispatch(new ValidationRecoveryLimitReached($retries, $errors));
        $this->events->dispatch(new ResponseGenerationFailed($errors));
        return Result::failure(new ValidationRecoveryLimitReached($retries-1, $errors));
    }

    protected function makeRetryMessages(
        array $messages,
        ResponseModel $responseModel,
        string $jsonData,
        array $errors
    ) : array {
        $messages[] = ['role' => 'assistant', 'content' => $jsonData];
        $messages[] = ['role' => 'user', 'content' => $responseModel->retryPrompt . ': ' . implode(", ", $errors)];
        return $messages;
    }

    private function registerStreamListeners(ResponseModel $requestedModel) : void {
        $this->events->addListener(
            eventClass: PartialJsonReceived::class,
            listener: function(PartialJsonReceived $event) use ($requestedModel) {
                $this->partialResponseHandler->handlePartialResponse($event->partialJson, $requestedModel);
            }
        );
        $this->events->addListener(
            eventClass: StreamedToolCallCompleted::class,
            listener: function(StreamedToolCallCompleted $event) use ($requestedModel) {
                $this->partialResponseHandler->finalizePartialResponse($requestedModel);
            }
        );
    }
}