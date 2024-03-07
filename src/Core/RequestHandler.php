<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanCallFunction;
use Cognesy\Instructor\Contracts\CanTransformResponse;
use Exception;

class RequestHandler
{
    public $retryPrompt = "Recall function correctly, fix following errors:";
    private CanCallFunction $llm;
    private ResponseModelFactory $responseModelFactory;

    public function __construct(
        CanCallFunction $llm,
        ResponseModelFactory $responseModelFactory,
    )
    {
        $this->llm = $llm;
        $this->responseModelFactory = $responseModelFactory;
    }

    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
     */
    public function respond(Request $request) : mixed {
        $requestedModel = $this->responseModelFactory->from(
            $request->responseModel
        );
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
            $json = $this->llm->callFunction(
                $messages,
                $responseModel->functionName,
                $responseModel->functionCall,
                $request->model,
                $request->options
            );
            [$object, $errors] = $responseModel->toResponse($json);
            if (empty($errors)) {
                if ($object instanceof CanTransformResponse) {
                    return $object->transform();
                }
                return $object;
            }
            $messages[] = ['role' => 'assistant', 'content' => $json];
            $messages[] = ['role' => 'user', 'content' => $this->retryPrompt . '\n' . $errors];
            $retries++;
        }
        throw new Exception("Failed to extract data due to validation constraints: " . $errors);
    }
}