<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleResponse;
use Cognesy\Instructor\Core\ResponseModel\ResponseModelFactory;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\ResponseGenerated;
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
            // we're streaming - let's yield partial responses (target objects wrapped in Result) from partial generator
            yield from $this->partialsGenerator->getPartialResponses($request, $responseModel, $this->messages);

            // ...and then get the final response
            $apiResponse = $this->partialsGenerator->getApiResponse();
            // we have ApiResponse here - let's process it: deserialize, validate, transform
            $responseProcessingResult = $this->responseHandler->handleResponse($apiResponse, $responseModel);
            if ($responseProcessingResult->isSuccess()) {
                $this->events->dispatch(new ResponseGenerated($responseProcessingResult->unwrap()));
                yield $responseProcessingResult;
            }

            // let's retry - as we have not managed to deserialize, validate or transform the response
            $errors = $responseProcessingResult->error();
            $this->partialsGenerator->resetPartialSequence();
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
}