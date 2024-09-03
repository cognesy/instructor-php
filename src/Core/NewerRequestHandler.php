<?php
namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\ApiClient\Contracts\CanCallLLM;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\ResponseGenerated;
use Cognesy\Instructor\Events\Request\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\Request\RequestSentToLLM;
use Cognesy\Instructor\Events\Request\RequestToLLMFailed;
use Cognesy\Instructor\Events\Request\ResponseReceivedFromLLM;
use Cognesy\Instructor\Events\Request\ValidationRecoveryLimitReached;
use Exception;
use Generator;

class NewerRequestHandler implements CanHandleRequest, CanHandleStreamRequest
{
    private int $retries = 0;
    private array $messages = [];
    private array $errors = [];

    public function __construct(
        private EventDispatcher     $events,
        private CanGenerateResponse $responseGenerator,
        private CanGeneratePartials $partialsGenerator,
    ) {}

    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
     * @param Request $request
     * @param bool $stream Whether to stream the response or not
     * @return mixed|Generator
     * @throws Exception
     */
    public function respondTo(Request $request, bool $stream = false): mixed
    {
        $responseModel = $request->responseModel();

        if ($responseModel === null) {
            throw new Exception("Request does not have a response model");
        }

        $this->retries = 0;
        $this->errors = [];

        while ($this->retries <= $request->maxRetries()) {
            if ($stream) {
                yield from $this->handleStreamedRequest($request);
            } else {
                $result = $this->handleSyncRequest($request);
                if ($result !== null) {
                    return $result;
                }
            }

            $this->retries++;
            if ($this->retries <= $request->maxRetries()) {
                $this->events->dispatch(new NewValidationRecoveryAttempt($this->retries, $this->errors));
            }
        }

        $this->events->dispatch(new ValidationRecoveryLimitReached($this->retries, $this->errors));
        throw new Exception("Validation recovery attempts limit reached after {$this->retries} attempts due to: " . implode(", ", $this->errors));
    }

    private function handleSyncRequest(Request $request): mixed
    {
        $apiResponse = $this->getApiResponse($request);
        $this->events->dispatch(new ResponseReceivedFromLLM($apiResponse));

        $processingResult = $this->responseGenerator->makeResponse($apiResponse, $request->responseModel());
        if ($processingResult->isSuccess()) {
            $value = $processingResult->unwrap();
            $request->setResponse($this->messages, $apiResponse, [], $value);
            $this->events->dispatch(new ResponseGenerated($value));
            return $value;
        }

        $this->errors = $processingResult->error();
        $request->addFailedResponse($this->messages, $apiResponse, [], $this->errors);
        return null;
    }

    private function handleStreamedRequest(Request $request): Generator
    {
        yield from $this->getStreamedResponses($request);

        $apiResponse = $this->partialsGenerator->getCompleteResponse();
        $this->events->dispatch(new ResponseReceivedFromLLM($apiResponse));

        $processingResult = $this->responseGenerator->makeResponse($apiResponse, $request->responseModel());
        if ($processingResult->isSuccess()) {
            $value = $processingResult->unwrap();
            $request->setResponse($this->messages, $apiResponse, $this->partialsGenerator->partialResponses(), $value);
            $this->events->dispatch(new ResponseGenerated($value));
            yield $value;
        } else {
            $this->errors = $processingResult->error();
            $request->addFailedResponse($this->messages, $apiResponse, $this->partialsGenerator->partialResponses(), $this->errors);
        }
    }

    private function getApiResponse(Request $request): ApiResponse
    {
        $apiClient = $this->getApiClient($request);
        $apiRequest = $request->toApiRequest();

        try {
            $this->events->dispatch(new RequestSentToLLM($apiRequest));
            $apiResponse = $apiClient->withApiRequest($apiRequest)->get();
        } catch (Exception $e) {
            $this->errors = [$e->getMessage()];
            $this->events->dispatch(new RequestToLLMFailed($apiClient->getApiRequest(), $e->getMessage()));
            throw $e;
        }

        return $apiResponse;
    }

    private function getStreamedResponses(Request $request): Generator
    {
        $apiClient = $this->getApiClient($request);
        $apiRequest = $request->toApiRequest();
        $this->messages = $request->messages();

        try {
            $this->events->dispatch(new RequestSentToLLM($apiRequest));
            $stream = $apiClient->withApiRequest($apiRequest)->stream();
            yield from $this->partialsGenerator->getPartialResponses($stream, $request->responseModel());
        } catch (Exception $e) {
            $this->errors = [$e->getMessage()];
            $this->events->dispatch(new RequestToLLMFailed($apiClient->getApiRequest(), $e->getMessage()));
            throw $e;
        }
    }

    private function getApiClient(Request $request): CanCallLLM
    {
        $apiClient = $request->client();
        if ($apiClient === null) {
            throw new Exception("Request does not have an API client");
        }
        return $apiClient;
    }
}