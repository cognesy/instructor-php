<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanHandlePartialResponse;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleResponse;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Data\LLMResponse;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\ValidationResult;
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
use Cognesy\Instructor\Exceptions\JSONParsingException;
use Cognesy\Instructor\Exceptions\ValidationException;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\JsonParser;
use Cognesy\Instructor\Utils\Result;
use Exception;

class RequestHandler implements CanHandleRequest
{
    private string $previousHash = '';
    private ?Sequenceable $lastPartialResponse;
    private int $previousSequenceLength = 1;

    public function __construct(
        private FunctionCallerFactory $functionCallerFactory,
        private ResponseModelFactory $responseModelFactory,
        private EventDispatcher $eventDispatcher,
        private CanHandleResponse $responseHandler,
        private CanHandlePartialResponse $partialResponseHandler,
    )
    {}

    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
     */
    public function respondTo(Request $request) : Result {
        $requestedModel = $this->responseModelFactory->fromRequest($request);
        if ($request->options['stream'] ?? false) {
            $this->registerStreamListeners($requestedModel);
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
            $llmCallResult = $functionCaller->callFunction(
                $messages,
                $responseModel,
                $request->model,
                $request->options
            );

            if ($llmCallResult->isSuccess()) {
                $this->eventDispatcher->dispatch(new FunctionCallResponseReceived($llmCallResult));
                // get response JSON data
                /** @var LLMResponse $llmResponse */
                $llmResponse = $llmCallResult->value();
                // TODO: handle multiple tool calls
                $jsonData = $llmResponse->functionCalls[0]->functionArgsJson ?? '';
                // TODO: END OF TODO
                // process LLM response
                $processingResult = $this->processResponse($jsonData, $responseModel);
                if ($processingResult->isSuccess()) {
                    return $processingResult;
                }
                $errors = $this->extractErrors($processingResult);
            }

            if ($llmCallResult->isFailure()) {
                $errors = $this->extractErrors($llmCallResult);
                $this->eventDispatcher->dispatch(new ResponseGenerationFailed($errors));
                if (!($llmCallResult->error() instanceof JsonParsingException)) {
                    // we don't handle errors other than JSONParsingException
                    return $llmCallResult;
                }
                $jsonData = $llmCallResult->error()->json;
            }

            // retry if validation failed
            $this->resetPartialResponse();
            $messages = $this->makeRetryMessages($messages, $responseModel, $jsonData, $errors);
            $retries++;
            if ($retries <= $request->maxRetries) {
                $this->eventDispatcher->dispatch(new NewValidationRecoveryAttempt($retries, $errors));
            }
        }
        $this->eventDispatcher->dispatch(new ValidationRecoveryLimitReached($retries, $errors));
        $this->eventDispatcher->dispatch(new ResponseGenerationFailed($errors));
        return Result::failure(new ValidationRecoveryLimitReached($retries-1, $errors));
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
            $errors = $this->extractErrors($result);
        } catch (ValidationException $e) {
            // handle uncaught validation exceptions
            $errors = $this->extractErrors($e);
        } catch (DeserializationException $e) {
            // handle uncaught deserialization exceptions
            $errors = $this->extractErrors($e);
        } catch (Exception $e) {
            // throw on other exceptions
            $this->eventDispatcher->dispatch(new ResponseGenerationFailed([$e->getMessage()]));
            throw new Exception($e->getMessage());
        }
        return Result::failure($errors);
    }

    protected function extractErrors(Result|Exception $result) : array {
        if ($result instanceof Exception) {
            return [$result->getMessage()];
        }
        if ($result->isSuccess()) {
            return [];
        }
        $errorValue = $result->error();
        return match($errorValue) {
            is_array($errorValue) => $errorValue,
            is_string($errorValue) => [$errorValue],
            $errorValue instanceof ValidationResult => [$errorValue->getErrorMessage()],
            $errorValue instanceof JSONParsingException => [$errorValue->message],
            $errorValue instanceof Exception => [$errorValue->getMessage()],
            default => [json_encode($errorValue)]
        };
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

    private function registerStreamListeners(ResponseModel $requestedModel)
    {
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

    protected function processPartialResponse(
        string $partialJsonData,
        ResponseModel $responseModel
    ) : void {
        $jsonData = (new JsonParser)->fix($partialJsonData);
        $result = $this->partialResponseHandler->toPartialResponse($jsonData, $responseModel);
        if ($result->isFailure()) {
            $errors = Arrays::toArray($result->error());
            $this->eventDispatcher->dispatch(new PartialResponseGenerationFailed($errors));
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

    protected function resetPartialResponse() : void {
        $this->previousHash = '';
        $this->lastPartialResponse = null;
        $this->previousSequenceLength = 1;
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
        if (!isset($this->lastPartialResponse)) {
            return;
        }
        if (!($this->lastPartialResponse instanceof Sequenceable)) {
            return;
        }
        $this->eventDispatcher->dispatch(new SequenceUpdated($this->lastPartialResponse));
    }
}