<?php

namespace Cognesy\Instructor\LLMs;

use Cognesy\Instructor\Events\LLM\PartialJsonReceived;
use Cognesy\Instructor\Events\LLM\RequestToLLMFailed;
use Cognesy\Instructor\Events\LLM\StreamedResponseReceived;
use Cognesy\Instructor\Exceptions\JSONParsingException;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\Result;

abstract class AbstractStreamedJsonHandler extends AbstractStreamedCallHandler
{
    protected string $responseText = '';
    protected string $responseJson = '';

    protected function processStream($stream) : Result {
        // process stream
        $functionCalls = [];
        $newFunctionCall = $this->newFunctionCall();
        $functionCalls[] = $newFunctionCall;
        foreach($stream as $response){
            // receive data
            $this->events->dispatch(new StreamedResponseReceived($response->toArray()));
            $this->lastResponse = $response;

            // skip if chunk empty
            $maybeArgumentChunk = $this->getArgumentChunk($response);
            if (empty($maybeArgumentChunk)) {
                continue;
            }

            // process response
            $this->responseText .= $maybeArgumentChunk;
            $this->responseJson = Json::findPartial($this->responseText);

            // fix this - change to Result based instead of exception based handling
            try {
                $this->validatePartialResponse($this->responseText);
            } catch (JsonParsingException $e) {
                return Result::failure($e);
            }

            $this->events->dispatch(new PartialJsonReceived($this->responseJson));
            $this->updateFunctionCall($functionCalls, $this->responseJson, $maybeArgumentChunk);
        }
        // check if there are any toolCalls
        if (count($functionCalls) === 0) {
            return Result::failure(new RequestToLLMFailed($this->request, 'No tool calls found in the response'));
        }
        // finalize last function call
        if (count($functionCalls) > 0) {
            $this->finalizeFunctionCall($functionCalls, Json::find($this->responseText));
        }
        return Result::success($functionCalls);
    }

    abstract protected function validatePartialResponse(string $partialResponseText) : void;
}