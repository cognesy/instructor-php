<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanCallFunction;
use Cognesy\Instructor\Contracts\CanTransformResponse;
use Cognesy\Instructor\Events\RequestHandler\RequestSentToLLM;
use Cognesy\Instructor\Events\RequestHandler\ResponseGenerated;
use Cognesy\Instructor\Events\RequestHandler\ResponseModelBuilt;
use Cognesy\Instructor\Events\RequestHandler\ResponseReceivedFromLLM;
use Cognesy\Instructor\Events\RequestHandler\ResponseTransformationFinished;
use Cognesy\Instructor\Events\RequestHandler\ResponseTransformationStarted;
use Cognesy\Instructor\Events\RequestHandler\ResponseValidationFailed;
use Exception;

class RequestHandler
{
    public $retryPrompt = "Recall function correctly, fix following errors:";
    private CanCallFunction $llm;
    private ResponseModelFactory $responseModelFactory;
    private EventDispatcher $eventDispatcher;

    public function __construct(
        CanCallFunction $llm,
        ResponseModelFactory $responseModelFactory,
        EventDispatcher $eventDispatcher
    )
    {
        $this->llm = $llm;
        $this->responseModelFactory = $responseModelFactory;
        $this->eventDispatcher = $eventDispatcher;
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
            $this->eventDispatcher->dispatch(new RequestSentToLLM(
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
            $this->eventDispatcher->dispatch(new ResponseReceivedFromLLM($response));
            $json = $response->toolCalls[0]->functionArguments;
            [$object, $errors] = $responseModel->toResponse($json);
            if (empty($errors)) {
                if ($object instanceof CanTransformResponse) {
                    $this->eventDispatcher->dispatch(new ResponseTransformationStarted($object));
                    $result = $object->transform();
                    $this->eventDispatcher->dispatch(new ResponseTransformationFinished($result));
                } else {
                    $result = $object;
                }
                $this->eventDispatcher->dispatch(new ResponseGenerated($result));
                return $result;
            }
            $this->eventDispatcher->dispatch(new ResponseValidationFailed($errors));
            $messages[] = ['role' => 'assistant', 'content' => $json];
            $messages[] = ['role' => 'user', 'content' => $this->retryPrompt . '\n' . $errors];
            $retries++;
        }
        throw new Exception("Failed to extract data due to validation constraints: " . $errors);
    }
}