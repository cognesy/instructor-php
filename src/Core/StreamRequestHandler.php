<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
use Cognesy\Instructor\Core\StreamResponse\PartialsGenerator;
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
use Generator;

class StreamRequestHandler implements CanHandleStreamRequest
{
    private int $retries = 0;
    private array $messages = [];

    public function __construct(
        private ResponseModelFactory $responseModelFactory,
        private EventDispatcher $events,
        private CanGenerateResponse $responseGenerator,
        private PartialsGenerator $partialsGenerator,
    ) {}

    /**
     * Returns response object or generator wrapped in Result monad
     */
    public function respondTo(Request $request) : Generator {
        $responseModel = $this->responseModelFactory->fromRequest($request);
        $this->events->dispatch(new ResponseModelBuilt($responseModel));
        // try to respond to the request until success or max retries reached
        $this->retries = 0;
        $this->messages = $request->messages();
        while ($this->retries <= $request->maxRetries) {
            // (0) process stream and return partial results...
            yield from $this->getStreamedResponses($request->copy($this->messages), $responseModel);

            // (1) ...then get API client response
            $apiResponse = $this->partialsGenerator->getCompleteResponse();
            $this->events->dispatch(new ResponseReceivedFromLLM($apiResponse));

            // (2) we have ApiResponse here - let's process it: deserialize, validate, transform
            $processingResult = $this->responseGenerator->makeResponse($apiResponse, $responseModel);
            if ($processingResult->isSuccess()) {
                // return final result
                yield $processingResult->unwrap();
                // we're done here - no need to retry
                return;
            }

            // (3) retry - we have not managed to deserialize, validate or transform the response
            $errors = $processingResult->error();
            $this->messages = $this->makeRetryMessages($this->messages, $request, $apiResponse->content, [$errors]);
            $this->retries++;
            if ($this->retries <= $request->maxRetries) {
                $this->events->dispatch(new NewValidationRecoveryAttempt($this->retries, $errors));
            }
            // (3.1) reset partials generator
            $this->partialsGenerator->resetPartialResponse();
        }
        $this->events->dispatch(new ValidationRecoveryLimitReached($this->retries, [$errors]));
        throw new Exception("Validation recovery attempts limit reached after {$this->retries} retries due to: ".implode(", ", $errors));
    }

    protected function getStreamedResponses(Request $request, ResponseModel $responseModel) : Generator {
        $apiClient = $request->client();
        $apiRequest = $apiClient->createApiRequest($request, $responseModel);
        try {
            $this->events->dispatch(new RequestSentToLLM($apiRequest));
            $stream = $apiClient->withApiRequest($apiRequest)->stream();
            yield from $this->partialsGenerator->getPartialResponses($stream, $responseModel, $this->messages);
        } catch(Exception $e) {
            $this->events->dispatch(new RequestToLLMFailed($apiClient->getApiRequest(), $e->getMessage()));
            throw $e;
        }
    }

    protected function makeRetryMessages(array $messages, Request $request, string $jsonData, array $errors) : array {
        $messages[] = ['role' => 'assistant', 'content' => $jsonData];
        $messages[] = ['role' => 'user', 'content' => $request->retryPrompt . ': ' . implode(", ", $errors)];
        return $messages;
    }
}