<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanCallFunction;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallRequested;
use Cognesy\Instructor\Events\RequestHandler\ResponseGenerationFailed;
use Cognesy\Instructor\Events\RequestHandler\ResponseModelBuilt;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallResponseReceived;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallResponseConvertedToObject;
use Cognesy\Instructor\Events\RequestHandler\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\RequestHandler\ValidationRecoveryLimitReached;
use Cognesy\Instructor\Exceptions\DeserializationException;
use Cognesy\Instructor\Exceptions\ValidationException;
use Exception;

class RequestHandler
{
    public $retryPrompt = "Recall function correctly, fix following errors:";
    private CanCallFunction $llm;
    private ResponseModelFactory $responseModelFactory;
    private EventDispatcher $eventDispatcher;
    private ResponseHandler $responseHandler;

    public function __construct(
        CanCallFunction $llm,
        ResponseModelFactory $responseModelFactory,
        EventDispatcher $eventDispatcher,
        ResponseHandler $responseHandler,
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
    public function respond(Request $request) : mixed {
        $requestedModel = $this->responseModelFactory->from(
            $request->responseModel
        );
        $this->eventDispatcher->dispatch(new ResponseModelBuilt($requestedModel));
        return $this->tryRespond($request, $requestedModel);
    }

    /**
     * Executes LLM call loop with validation until success or max retries reached
     */
    protected function tryRespond(
        Request $request,
        ResponseModel $responseModel,
    ) : mixed {
        $retries = 0;
        $messages = $request->messages();
        while ($retries <= $request->maxRetries) {
            $this->eventDispatcher->dispatch(new FunctionCallRequested(
                $messages,
                $responseModel,
                $request
            ));
            $response = $this->llm->callFunction(
                $messages,
                $responseModel->functionName,
                $responseModel->functionCall,
                $request->model,
                $request->options
            );
            $this->eventDispatcher->dispatch(new FunctionCallResponseReceived($response));
            $json = $response->toolCalls[0]->functionArguments;
            try {
                $result = $this->responseHandler->toResponse($responseModel, $json);
                if ($result->isSuccess()) {
                    $object = $result->value();
                    $this->eventDispatcher->dispatch(new FunctionCallResponseConvertedToObject($object));
                    return $object;
                }
                $errors = $result->errorValue();
            } catch (ValidationException $e) {
                $errors = [$e->getMessage()];
            } catch (DeserializationException $e) {
                $errors = [$e->getMessage()];
            } catch (Exception $e) {
                $this->eventDispatcher->dispatch(new ResponseGenerationFailed($request, $e->getMessage()));
                throw $e;
            }
            // TODO: this is workaround for now, find the source of bug
            // something is not returning array of errors, but a DeserializationException
            if (!is_array($errors)) {
                $errors = [$errors->getMessage()];
            }
            $messages[] = ['role' => 'assistant', 'content' => $json];
            $messages[] = ['role' => 'user', 'content' => $this->retryPrompt . ': ' . implode(", ", $errors)];
            $retries++;
            if ($retries <= $request->maxRetries) {
                $this->eventDispatcher->dispatch(new NewValidationRecoveryAttempt($retries, $errors));
            }
        }
        $this->eventDispatcher->dispatch(new ValidationRecoveryLimitReached($retries, $errors));
        throw new Exception("Failed to extract data due to validation errors: " . implode(", ", $errors));
    }
}