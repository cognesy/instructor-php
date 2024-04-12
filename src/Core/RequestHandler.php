<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Core\ResponseModel\ResponseModelFactory;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\RequestHandler\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\RequestHandler\ResponseGenerationFailed;
use Cognesy\Instructor\Events\RequestHandler\ResponseModelBuilt;
use Cognesy\Instructor\Events\RequestHandler\ToolCallRequested;
use Cognesy\Instructor\Events\RequestHandler\ToolCallResponseReceived;
use Cognesy\Instructor\Events\RequestHandler\ValidationRecoveryLimitReached;
use Cognesy\Instructor\Utils\Result;

class RequestHandler implements CanHandleRequest
{
    public function __construct(
        private ResponseModelFactory $responseModelFactory,
        private EventDispatcher      $events,
        private CanGenerateResponse  $responseGenerator,
        private RequestBuilder       $requestBuilder,
    ) {}

    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
     */
    public function respondTo(Request $request) : Result {
        $responseModel = $this->responseModelFactory->fromRequest($request);
        $this->events->dispatch(new ResponseModelBuilt($responseModel));
        $retries = 0;
        $messages = $request->messages();
        while ($retries <= $request->maxRetries) {
            $this->events->dispatch(new ToolCallRequested($messages, $responseModel, $request));
            // get function caller instance
            $apiClientRequest = $this->requestBuilder->makeClientRequest(
                $messages, $responseModel, $request->model, $request->options, $request->mode
            );

            // run LLM inference
            $response = $apiClientRequest->get();
            $this->events->dispatch(new ToolCallResponseReceived($response));
            $processingResult = $this->responseGenerator->makeResponse($response, $responseModel);
            if ($processingResult->isSuccess()) {
                return $processingResult;
            }
            $errors = $processingResult->error();

            // retry if validation failed
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
}
