<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanCallFunction;
use Cognesy\Instructor\Contracts\CanTransformResponse;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallRequested;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallResultReady;
use Cognesy\Instructor\Events\RequestHandler\ResponseGenerationFailed;
use Cognesy\Instructor\Events\RequestHandler\ResponseModelBuilt;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallResponseReceived;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallResponseTransformed;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallResponseConvertedToObject;
use Cognesy\Instructor\Events\RequestHandler\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\RequestHandler\ValidationRecoveryLimitReached;
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
            [$object, $errors] = $this->responseHandler->toResponse($responseModel, $json);
            if (empty($errors)) {
                $this->eventDispatcher->dispatch(new FunctionCallResponseConvertedToObject($object));
                if ($object instanceof CanTransformResponse) {
                    $result = $object->transform();
                    $this->eventDispatcher->dispatch(new FunctionCallResponseTransformed($result));
                } else {
                    $result = $object;
                }
                $this->eventDispatcher->dispatch(new FunctionCallResultReady($result));
                return $result;
            }
            $messages[] = ['role' => 'assistant', 'content' => $json];
            $messages[] = ['role' => 'user', 'content' => $this->retryPrompt . '\n' . $errors];
            $retries++;
            if ($retries <= $request->maxRetries) {
                $this->eventDispatcher->dispatch(new NewValidationRecoveryAttempt($retries, $errors));
            }
        }
        $this->eventDispatcher->dispatch(new ValidationRecoveryLimitReached($retries, $errors));
        throw new Exception("Failed to extract data due to validation constraints: " . $errors);
    }
}