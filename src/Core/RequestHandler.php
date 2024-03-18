<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanCallFunction;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleResponse;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Core\Data\Request;
use Cognesy\Instructor\Core\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\LLM\PartialJsonReceived;
use Cognesy\Instructor\Events\LLM\StreamedFunctionCallCompleted;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallRequested;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallResponseConvertedToObject;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallResponseReceived;
use Cognesy\Instructor\Events\RequestHandler\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\RequestHandler\PartialResponseGenerated;
use Cognesy\Instructor\Events\RequestHandler\PartialResponseGenerationFailed;
use Cognesy\Instructor\Events\RequestHandler\ResponseGenerationFailed;
use Cognesy\Instructor\Events\RequestHandler\ResponseModelBuilt;
use Cognesy\Instructor\Events\RequestHandler\SequenceUpdated;
use Cognesy\Instructor\Events\RequestHandler\ValidationRecoveryLimitReached;
use Cognesy\Instructor\Exceptions\DeserializationException;
use Cognesy\Instructor\Exceptions\ValidationException;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\JsonParser;
use Cognesy\Instructor\Utils\Result;
use Exception;

class RequestHandler implements CanHandleRequest
{
    private string $previousHash = '';
    private Sequenceable $lastPartialResponse;
    private int $previousSequenceLength = 1;

    public function __construct(
        private FunctionCallerFactory $functionCallerFactory,
        private ResponseModelFactory $responseModelFactory,
        private EventDispatcher $eventDispatcher,
        private CanHandleResponse $responseHandler,
    )
    {}

    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
     */
    public function respondTo(Request $request) : Result {
        $requestedModel = $this->responseModelFactory->fromRequest($request);
        if ($request->options['stream'] ?? false) {
            $this->eventDispatcher->addListener(
                eventClass: PartialJsonReceived::class,
                listener: function(PartialJsonReceived $event) use ($requestedModel) {
                    $this->processPartialResponse($event->partialJson, $requestedModel);
                }
            );
            $this->eventDispatcher->addListener(
                eventClass: StreamedFunctionCallCompleted::class,
                listener: function(StreamedFunctionCallCompleted $event) use ($requestedModel) {
                    $this->finalizePartialResponse($requestedModel);
                }
            );
        }
        $this->eventDispatcher->dispatch(new ResponseModelBuilt($requestedModel));
        return $this->tryRespond($request, $requestedModel);
    }

    /**
     * Executes LLM call loop with validation until success or max retries reached
     */
    protected function tryRespond(
        Request $request,
        ResponseModel $responseModel,
    ) : Result {
        $retries = 0;
        $messages = $request->messages();
        while ($retries <= $request->maxRetries) {
            // get function caller instance
            $functionCaller = $this->functionCallerFactory->fromRequest($request);
            $this->eventDispatcher->dispatch(new FunctionCallRequested($messages, $responseModel, $request));

            // run LLM inference
            $llmResult = $functionCaller->callFunction(
                $messages,
                $responseModel,
                $request->model,
                $request->options
            );
            if ($llmResult->isFailure()) {
                $this->eventDispatcher->dispatch(new ResponseGenerationFailed(Arrays::toArray($llmResult->error())));
                return $llmResult;
            }
            $this->eventDispatcher->dispatch(new FunctionCallResponseReceived($llmResult));

            // get response JSON data
            // TODO: handle multiple tool calls
            $jsonData = $llmResult->value()->toolCalls[0]->functionArguments ?? '';
            // TODO: END OF TODO

            // process LLM response
            $processingResult = $this->processResponse($jsonData, $responseModel);
            if ($processingResult->isSuccess()) {
                return $processingResult;
            }

            // retry if validation failed
            $errors = $processingResult->error();
            $messages = $this->makeRetryMessages($messages, $responseModel, $jsonData, $errors);
            $retries++;
            if ($retries <= $request->maxRetries) {
                $this->eventDispatcher->dispatch(new NewValidationRecoveryAttempt($retries, $errors));
            }
        }
        $this->eventDispatcher->dispatch(new ValidationRecoveryLimitReached($retries, $errors));
        $this->eventDispatcher->dispatch(new ResponseGenerationFailed($errors));
        return Result::failure(new ValidationRecoveryLimitReached($retries, $errors));
    }

    protected function processResponse(string $jsonData, ResponseModel $responseModel) : Result {
        // check if JSON not empty and not malformed
        try {
            $result = $this->responseHandler->toResponse($jsonData, $responseModel);
            if ($result->isSuccess()) {
                $object = $result->value();
                $this->eventDispatcher->dispatch(new FunctionCallResponseConvertedToObject($object));
                return Result::success($object);
            }
            $errors = Arrays::toArray($result->error());
        } catch (ValidationException $e) {
            // handle uncaught validation exceptions
            $errors = [$e->getMessage()];
        } catch (DeserializationException $e) {
            // handle uncaught deserialization exceptions
            $errors = [$e->getMessage()];
        } catch (Exception $e) {
            // throw on other exceptions
            $this->eventDispatcher->dispatch(new ResponseGenerationFailed([$e->getMessage()]));
            throw new Exception($e->getMessage());
        }
        return Result::failure($errors);
    }

    protected function processPartialResponse(string $partialJsonData, ResponseModel $responseModel) : void {
        $jsonData = (new JsonParser)->fix($partialJsonData);
        $result = $this->responseHandler->toPartialResponse($jsonData, $responseModel);
        if ($result->isFailure()) {
            $errors = Arrays::toArray($result->error());
            $this->eventDispatcher->dispatch(new PartialResponseGenerationFailed($errors));
            //throw new Exception(implode(';', $errors));
            return;
        }

        // proceed if converting to object was successful
        $partialResponse = clone $result->value();
        $currentHash = hash('xxh3', json_encode($partialResponse));
        if ($this->previousHash != $currentHash) {
            // send partial response to listener only if new tokens changed resulting response object
            $this->eventDispatcher->dispatch(new PartialResponseGenerated($partialResponse));
            if (($partialResponse instanceof Sequenceable)) {
                $this->processSequenceable($partialResponse);
                $this->lastPartialResponse = clone $partialResponse;
            }
            $this->previousHash = $currentHash;
        }
    }

    protected function processSequenceable(Sequenceable $partialResponse) : void {
        $currentLength = count($partialResponse);
        if ($currentLength <= $this->previousSequenceLength) {
            return;
        }
        $this->previousSequenceLength = $currentLength;
        $this->eventDispatcher->dispatch(new SequenceUpdated($this->lastPartialResponse));
    }

    protected function finalizePartialResponse(ResponseModel $responseModel) : void {
        if (
            !isset($this->lastPartialResponse)
            || !($this->lastPartialResponse instanceof Sequenceable)
        ) {
            return;
        }
        $this->eventDispatcher->dispatch(new SequenceUpdated($this->lastPartialResponse));
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