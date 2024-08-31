<?php
namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\ApiClient\Contracts\CanCallLLM;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\ResponseGenerated;
use Cognesy\Instructor\Events\Request\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\Request\RequestSentToLLM;
use Cognesy\Instructor\Events\Request\RequestToLLMFailed;
use Cognesy\Instructor\Events\Request\ResponseReceivedFromLLM;
use Cognesy\Instructor\Events\Request\ValidationRecoveryLimitReached;
use Exception;

class RequestHandler implements CanHandleRequest
{
    private int $retries = 0;
    private array $messages = [];

    public function __construct(
        private EventDispatcher $events,
        private CanGenerateResponse $responseGenerator,
    ) {}

    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
     */
    public function respondTo(Request $request) : mixed {
        $responseModel = $request->responseModel();

        if ($responseModel === null) {
            throw new Exception("Request does not have a response model");
        }
        // try to respond to the request until success or max retries reached
        $this->retries = 0;
        while ($this->retries <= $request->maxRetries()) {
            // (1) get the API client response
            $apiResponse = $this->getApiResponse($request);
            $this->messages = $request->messages(); // TODO: tx messages to Scripts
            $this->events->dispatch(new ResponseReceivedFromLLM($apiResponse));

            // (2) we have ApiResponse here - let's process it: deserialize, validate, transform
            $processingResult = $this->responseGenerator->makeResponse($apiResponse, $responseModel);
            if ($processingResult->isSuccess()) {
                // get final value
                $value = $processingResult->unwrap();
                // store response
                $request->setResponse($this->messages, $apiResponse, [], $value); // TODO: tx messages to Scripts
                // notify on response generation
                $this->events->dispatch(new ResponseGenerated($value));
                // we're done here - no need to retry
                return $value;
            }

            // (3) retry - we have not managed to deserialize, validate or transform the response
            $errors = $processingResult->error();

            // store failed response
            $request->addFailedResponse($this->messages, $apiResponse, [], [$errors]); // TODO: tx messages to Scripts
            $this->retries++;
            if ($this->retries <= $request->maxRetries()) {
                $this->events->dispatch(new NewValidationRecoveryAttempt($this->retries, $errors));
            }
        }
        $errors = $errors ?? [];
        $this->events->dispatch(new ValidationRecoveryLimitReached($this->retries, $errors));
        throw new Exception("Validation recovery attempts limit reached after {$this->retries} attempts due to: ".implode(", ", $errors));
    }


    // INTERNAL ////////////////////////////////////////////////////////


    protected function getApiResponse(Request $request) : ApiResponse {
        /** @var CanCallLLM $apiClient */
        $apiClient = $request->client();
        if ($apiClient === null) {
            throw new Exception("Request does not have an API client");
        }
        $apiRequest = $request->toApiRequest();
        try {
            $this->events->dispatch(new RequestSentToLLM($apiRequest));
            $apiResponse = $apiClient->withApiRequest($apiRequest)->get();
        } catch (Exception $e) {
            $this->events->dispatch(new RequestToLLMFailed($apiClient->getApiRequest(), $e->getMessage()));
            throw $e;
        }
        return $apiResponse;
    }
}
