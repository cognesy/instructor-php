<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanCallFunction;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleResponse;
use Cognesy\Instructor\Core\Data\Request;
use Cognesy\Instructor\Core\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\LLM\PartialJsonReceived;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallRequested;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallResponseConvertedToObject;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallResponseReceived;
use Cognesy\Instructor\Events\RequestHandler\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\RequestHandler\PartialResponseGenerated;
use Cognesy\Instructor\Events\RequestHandler\PartialResponseGenerationFailed;
use Cognesy\Instructor\Events\RequestHandler\ResponseGenerationFailed;
use Cognesy\Instructor\Events\RequestHandler\ResponseModelBuilt;
use Cognesy\Instructor\Events\RequestHandler\ValidationRecoveryLimitReached;
use Cognesy\Instructor\Exceptions\DeserializationException;
use Cognesy\Instructor\Exceptions\ValidationException;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\JsonParser;
use Cognesy\Instructor\Utils\Result;
use Exception;

class RequestHandler implements CanHandleRequest
{
    private CanCallFunction $llm;
    private ResponseModelFactory $responseModelFactory;
    private EventDispatcher $eventDispatcher;
    private CanHandleResponse $responseHandler;

    public function __construct(
        CanCallFunction      $llm,
        ResponseModelFactory $responseModelFactory,
        EventDispatcher      $eventDispatcher,
        CanHandleResponse    $responseHandler,
    )
    {
        $this->llm = $llm;
        $this->responseModelFactory = $responseModelFactory;
        $this->eventDispatcher = $eventDispatcher;
        $this->responseHandler = $responseHandler;
    }

    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
     */
    public function respondTo(Request $request) : Result {
        $requestedModel = $this->responseModelFactory->fromRequest($request);
        if ($request->options['stream'] ?? false) {
            $this->eventDispatcher->addListener(
                PartialJsonReceived::class,
                function(PartialJsonReceived $event) use ($requestedModel) {
                    $this->processPartialResponse($event->partialJson, $requestedModel);
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
            // run LLM inference
            $this->eventDispatcher->dispatch(new FunctionCallRequested($messages, $responseModel, $request));
            $llmResult = $this->llm->callFunction(
                $messages,
                $responseModel->functionName,
                $responseModel->functionCall,
                $request->model,
                $request->options
            );
            if ($llmResult->isFailure()) {
                $this->eventDispatcher->dispatch(new ResponseGenerationFailed(Arrays::toArray($llmResult->error())));
                return $llmResult;
            }

            // process LLM response
            $this->eventDispatcher->dispatch(new FunctionCallResponseReceived($llmResult));
            $jsonData = $llmResult->value()->toolCalls[0]->functionArguments;
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
        if ($result->isSuccess()) {
            $partialResponse = $result->value();
            $this->eventDispatcher->dispatch(new PartialResponseGenerated($partialResponse));
        } else {
            $errors = Arrays::toArray($result->error());
            $this->eventDispatcher->dispatch(new PartialResponseGenerationFailed($errors));
        }
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