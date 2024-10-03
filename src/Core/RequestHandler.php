<?php
namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
use Cognesy\Instructor\Contracts\CanHandleSyncRequest;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\ResponseGenerated;
use Cognesy\Instructor\Events\Request\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\Request\RequestSentToLLM;
use Cognesy\Instructor\Events\Request\RequestToLLMFailed;
use Cognesy\Instructor\Events\Request\ResponseReceivedFromLLM;
use Cognesy\Instructor\Events\Request\ValidationRecoveryLimitReached;
use Cognesy\Instructor\Extras\LLM\Data\ApiResponse;
use Cognesy\Instructor\Extras\LLM\Inference;
use Cognesy\Instructor\Extras\LLM\InferenceResponse;
use Cognesy\Instructor\Utils\Json\Json;
use Cognesy\Instructor\Utils\Result\Result;
use Exception;
use Generator;

class RequestHandler implements CanHandleSyncRequest, CanHandleStreamRequest
{
    protected EventDispatcher $events;
    protected int $retries = 0;
    protected array $messages = [];
    protected array $errors = [];
    protected ?ResponseModel $responseModel;

    public function __construct(
        protected CanGenerateResponse $responseGenerator,
        protected CanGeneratePartials $partialsGenerator,
        EventDispatcher $events,
    ) {
        $this->events = $events;
    }

    /**
     * Generates response value
     */
    public function responseFor(Request $request) : mixed {
        $this->init($request);

        $processingResult = Result::failure("No response generated");
        while ($processingResult->isFailure() && !$this->maxRetriesReached($request)) {
            $apiResponse = $this->getApiResponse($request);
            $partialResponses = [];
            $processingResult = $this->processResponse($request, $apiResponse, $partialResponses);
        }

        $value = $this->processResult($processingResult, $request, $apiResponse, $partialResponses);

        return $value;
    }

    /**
     * Yields response value versions based on streamed responses
     */
    public function streamResponseFor(Request $request) : Generator {
        $this->init($request);

        $processingResult = Result::failure("No response generated");
        while ($processingResult->isFailure() && !$this->maxRetriesReached($request)) {
            yield from $this->getStreamedResponses($request);

            $apiResponse = $this->partialsGenerator->getCompleteResponse();
            $partialResponses = $this->partialsGenerator->partialResponses();
            $processingResult = $this->processResponse($request, $apiResponse, $partialResponses);
        }

        $value = $this->processResult($processingResult, $request, $apiResponse, $partialResponses);

        yield $value;
    }

    // INTERNAL ////////////////////////////////////////////////////////

    protected function init(Request $request) : void {
        $this->responseModel = $request->responseModel();
        if ($this->responseModel === null) {
            throw new Exception("Request does not have a response model");
        }

        $this->retries = 0;
        $this->messages = $request->messages(); // TODO: tx messages to Scripts
        $this->errors = [];
    }

    protected function getApiResponse(Request $request) : ApiResponse {
        try {
            $this->events->dispatch(new RequestSentToLLM($request));
            $apiResponse = $this->makeInference($request)->toApiResponse();
            $apiResponse->content = match($request->mode()) {
                Mode::Text => $apiResponse->content,
                default => Json::from($apiResponse->content)->toString(),
            };
        } catch (Exception $e) {
            $this->events->dispatch(new RequestToLLMFailed($request, $e->getMessage()));
            throw $e;
        }
        return $apiResponse;
    }

    /**
     * @param Request $request
     * @return Generator<mixed>
     */
    protected function getStreamedResponses(Request $request) : Generator {
        try {
            $this->events->dispatch(new RequestSentToLLM($request));
            $stream = $this->makeInference($request)->toPartialApiResponses();
            yield from $this->partialsGenerator->getPartialResponses($stream, $request->responseModel());
        } catch(Exception $e) {
            $this->events->dispatch(new RequestToLLMFailed($request, $e->getMessage()));
            throw $e;
        }
    }

    protected function makeInference(Request $request) : InferenceResponse {
        $inference = new Inference(
            connection: $request->connection(),
            httpClient: $request->httpClient(),
            driver: $request->driver(),
            events: $this->events,
        );
        return $inference
            ->create(
                $request->toMessages(),
                $request->model(),
                $request->toolCallSchema(),
                $request->toolChoice(),
                $request->responseFormat(),
                $request->options(),
                $request->mode()
            );
    }

    protected function processResponse(Request $request, ApiResponse $apiResponse, array $partialResponses) : Result {
        $this->events->dispatch(new ResponseReceivedFromLLM($apiResponse));

        // we have ApiResponse here - let's process it: deserialize, validate, transform
        $processingResult = $this->responseGenerator->makeResponse($apiResponse, $this->responseModel);

        if ($processingResult->isFailure()) {
            // (3) retry - we have not managed to deserialize, validate or transform the response
            $this->handleError($processingResult, $request, $apiResponse, $partialResponses);
        }

        return $processingResult;
    }

    protected function processResult(Result $processingResult, Request $request, ApiResponse $apiResponse, array $partialResponses) : mixed {
        if ($processingResult->isFailure()) {
            $this->events->dispatch(new ValidationRecoveryLimitReached($this->retries, $this->errors));
            throw new Exception("Validation recovery attempts limit reached after {$this->retries} attempts due to: ".implode(", ", $this->errors));
        }

        // get final value
        $value = $processingResult->unwrap();
        // store response
        $request->setResponse($this->messages, $apiResponse, $partialResponses, $value); // TODO: tx messages to Scripts
        // notify on response generation
        $this->events->dispatch(new ResponseGenerated($value));

        return $value;
    }

    protected function handleError(Result $processingResult, Request $request, ApiResponse $apiResponse, array $partialResponses) : void {
        $error = $processingResult->error();
        $this->errors = is_array($error) ? $error : [$error];

        // store failed response
        $request->addFailedResponse($this->messages, $apiResponse, $partialResponses, $this->errors); // TODO: tx messages to Scripts
        $this->retries++;
        if ($this->retries <= $request->maxRetries()) {
            $this->events->dispatch(new NewValidationRecoveryAttempt($this->retries, $this->errors));
        }
    }

    protected function maxRetriesReached(Request $request) : bool {
        return $this->retries > $request->maxRetries();
    }
}
