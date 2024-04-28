<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Request\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\Request\RequestSentToLLM;
use Cognesy\Instructor\Events\Request\RequestToLLMFailed;
use Cognesy\Instructor\Events\Request\ResponseModelBuilt;
use Cognesy\Instructor\Events\Request\ResponseReceivedFromLLM;
use Cognesy\Instructor\Events\Request\ValidationRecoveryLimitReached;
use Exception;

class RequestHandler implements CanHandleRequest
{
    private int $retries = 0;
    private array $messages = [];

    public function __construct(
        private ResponseModelFactory $responseModelFactory,
        private EventDispatcher      $events,
        private CanGenerateResponse  $responseGenerator,
    ) {}

    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
     */
    public function respondTo(Request $request) : mixed {
        $responseModel = $this->responseModelFactory->fromRequest($request);
        $this->events->dispatch(new ResponseModelBuilt($responseModel));
        // try to respond to the request until success or max retries reached
        $this->retries = 0;
        $this->messages = $request->messages();
        while ($this->retries <= $request->maxRetries) {
            // (1) get the API client response
            $apiResponse = $this->getResponse($this->messages, $request, $responseModel);
            $this->events->dispatch(new ResponseReceivedFromLLM($apiResponse));

            // (2) we have ApiResponse here - let's process it: deserialize, validate, transform
            $processingResult = $this->responseGenerator->makeResponse($apiResponse, $responseModel);
            if ($processingResult->isSuccess()) {
                // we're done here - no need to retry
                return $processingResult->unwrap();
            }

            // (3) retry - we have not managed to deserialize, validate or transform the response
            $errors = $processingResult->error();
            $this->messages = $this->makeRetryMessages($this->messages, $request, $apiResponse->content, $errors);
            $this->retries++;
            if ($this->retries <= $request->maxRetries) {
                $this->events->dispatch(new NewValidationRecoveryAttempt($this->retries, $errors));
            }
        }
        $this->events->dispatch(new ValidationRecoveryLimitReached($this->retries, $errors));
        throw new Exception("Validation recovery attempts limit reached after {$this->retries} retries due to: ".implode(", ", $errors));
    }

    protected function getResponse(array $messages, Request $request, ResponseModel $responseModel) : ApiResponse {
        $apiClient = $request->client()->addRequest($messages, $responseModel, $request);
        try {
            $this->events->dispatch(new RequestSentToLLM($apiClient->getRequest()));
            $apiResponse = $apiClient->get();
        } catch (Exception $e) {
            $this->events->dispatch(new RequestToLLMFailed($apiClient->getRequest(), $e->getMessage()));
            throw $e;
        }
        return $apiResponse;
    }

    protected function makeRetryMessages(
        array $messages, Request $request, string $jsonData, array $errors
    ) : array {
        $messages[] = ['role' => 'assistant', 'content' => $jsonData];
        $messages[] = ['role' => 'user', 'content' => $request->retryPrompt . ': ' . implode(", ", $errors)];
        return $messages;
    }
}
