<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleResponse;
use Cognesy\Instructor\Core\ResponseModel\ResponseModelFactory;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\ResponseGenerated;
use Cognesy\Instructor\Events\LLM\RequestSentToLLM;
use Cognesy\Instructor\Events\LLM\RequestToLLMFailed;
use Cognesy\Instructor\Events\RequestHandler\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\RequestHandler\ResponseGenerationFailed;
use Cognesy\Instructor\Events\RequestHandler\ResponseModelBuilt;
use Cognesy\Instructor\Events\RequestHandler\ValidationRecoveryLimitReached;
use Exception;
use Generator;

class StreamRequestHandler implements CanHandleRequest
{
    private int $retries = 0;
    private array $messages = [];

    public function __construct(
        private ResponseModelFactory $responseModelFactory,
        private EventDispatcher      $events,
        private CanHandleResponse    $responseHandler,
        private PartialsGenerator    $partialsGenerator,
        private RequestBuilder       $requestBuilder,
    ) {}

    /**
     * Returns response object or generator wrapped in Result monad
     */
    public function respondTo(Request $request) : Generator {
        $isStreamingRequested = $request->options['stream'] ?? false;
        if (!$isStreamingRequested) {
            throw new Exception('Streaming is not requested');
        }
        $responseModel = $this->responseModelFactory->fromRequest($request);
        $this->events->dispatch(new ResponseModelBuilt($responseModel));
        $this->retries = 0;
        $this->messages = $request->messages();
        while ($this->retries <= $request->maxRetries) {
            // get stream from client for current set of messages (updated on each retry)
            $stream = $this->getStream($this->messages, $responseModel, $request);
            // stream responses (target objects wrapped in Result) from partial generator
            foreach($this->partialsGenerator->getPartialResponses($stream, $responseModel, $this->messages) as $update) {
                yield $update;
            }

            // ...and then get the final response
            $apiResponse = $this->partialsGenerator->getCompleteResponse();
            // we have ApiResponse here - let's process it: deserialize, validate, transform
            $responseProcessingResult = $this->responseHandler->handleResponse($apiResponse, $responseModel);
            if ($responseProcessingResult->isSuccess()) {
                $this->events->dispatch(new ResponseGenerated($responseProcessingResult->unwrap()));
                yield $responseProcessingResult->unwrap();
                return;
            }

            // let's retry - as we have not managed to deserialize, validate or transform the response
            $errors = $responseProcessingResult->error();
            $this->partialsGenerator->resetPartialResponse();
            $this->messages = $this->makeRetryMessages($this->messages, $responseModel, $apiResponse->content, [$errors]);
            $this->retries++;
            if ($this->retries <= $request->maxRetries) {
                $this->events->dispatch(new NewValidationRecoveryAttempt($this->retries, $errors));
            } else {
                $this->events->dispatch(new ValidationRecoveryLimitReached($this->retries, [$errors]));
                $this->events->dispatch(new ResponseGenerationFailed([$errors]));
                throw new Exception("Validation recovery attempts limit reached after {$this->retries} retries due to: $errors");
            }
        }
    }

    protected function makeRetryMessages(array $messages, ResponseModel $responseModel, string $jsonData, array $errors) : array {
        $messages[] = ['role' => 'assistant', 'content' => $jsonData];
        $messages[] = ['role' => 'user', 'content' => $responseModel->retryPrompt . ': ' . implode(", ", $errors)];
        return $messages;
    }

    protected function getStream(array $messages, ResponseModel $responseModel, Request $request) : Generator {
        // get function caller instance
        /** @var ApiClient $apiCallRequest */
        $apiCallRequest = $this->requestBuilder->makeClientRequest(
            $messages, $responseModel, $request->model, $request->options, $request->mode
        );
        try {
            $this->events->dispatch(new RequestSentToLLM($apiCallRequest->getRequest()));
            return $apiCallRequest->stream();
        } catch(Exception $e) {
            $this->events->dispatch(new RequestToLLMFailed([], $e->getMessage()));
            throw new Exception($e->getMessage());
        }
    }
}