<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleResponse;
use Cognesy\Instructor\Core\ApiClient\ToolCallerFactory;
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
use Exception;
use Generator;

class ResponseGenerator implements CanHandleRequest
{
    public function __construct(
        private ToolCallerFactory    $toolCallerFactory,
        private ResponseModelFactory $responseModelFactory,
        private EventDispatcher      $events,
        private CanHandleResponse    $responseHandler,
        private PartialsGenerator    $partialsGenerator,
    ) {}

    /**
     * Returns response object or generator wrapped in Result monad
     */
    public function respondTo(Request $request) : Generator {
        $isStreamingRequested = $request->options['stream'] ?? false;
        $responseModel = $this->responseModelFactory->fromRequest($request);
        $this->events->dispatch(new ResponseModelBuilt($responseModel));
        $retries = 0;
        $messages = $request->messages();
        $responseProcessingResult = null;
        while ($retries <= $request->maxRetries) {
            // get function caller instance
            $clientCaller = $this->toolCallerFactory->fromRequest($request);
            // run LLM inference
            /** @var ApiClient $apiCallRequest */
            $this->events->dispatch(new ToolCallRequested($messages, $responseModel, $request));
            $apiCallRequest = $clientCaller->callApiClient($messages, $responseModel, $request->model, $request->options);
            if (!$isStreamingRequested) {
                $apiResponse = $this->getResponse($apiCallRequest);
            } else {
                // we're streaming - let's yield partial responses (target objects!) from partial generator
                yield $this->partialsGenerator->getPartialResponses($apiCallRequest, $request, $responseModel);
                // ...and then get the final response
                $apiResponse = $this->partialsGenerator->getApiResponse();
            }
            // we have ApiResponse here - let's process it: deserialize, validate, transform
            $responseProcessingResult = $this->responseHandler->handleResponse($apiResponse, $responseModel);
            if ($responseProcessingResult->isSuccess()) {
                break;
            }
            // let's retry - as we have not managed to deserialize, validate or transform the response
            $errors = $responseProcessingResult->error();
            $this->partialsGenerator->resetPartialSequence();
            $messages = $this->makeRetryMessages($messages, $responseModel, $apiResponse->content, $errors);
            $retries++;
            if ($retries <= $request->maxRetries) {
                $this->events->dispatch(new NewValidationRecoveryAttempt($retries, $errors));
            } else {
                $this->events->dispatch(new ValidationRecoveryLimitReached($retries, $errors));
                $this->events->dispatch(new ResponseGenerationFailed($errors));
                throw new Exception("Validation recovery attempts limit reached after {$retries} retries due to: $errors");
            }
        }
        yield $responseProcessingResult->unwrap();
    }

    private function getResponse(ApiClient $apiCallRequest) : ApiResponse
    {
        //fixme: $this->events->dispatch(new RequestSentToLLM($clientWithRequest->getRequest()));
        try {
            /** @var ApiResponse $response */
            $response = $apiCallRequest->get();
        } catch(Exception $e) {
            throw new Exception('Failed to get response from API');
        }
        $this->events->dispatch(new ToolCallResponseReceived($response));
        return $response;
    }

    protected function makeRetryMessages(array $messages, ResponseModel $responseModel, string $jsonData, array $errors) : array {
        $messages[] = ['role' => 'assistant', 'content' => $jsonData];
        $messages[] = ['role' => 'user', 'content' => $responseModel->retryPrompt . ': ' . implode(", ", $errors)];
        return $messages;
    }
}