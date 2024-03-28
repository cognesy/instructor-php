<?php
namespace Cognesy\Instructor\LLMs;

use Cognesy\Instructor\ApiClient\Data\Responses\PartialApiResponse;
use Cognesy\Instructor\Events\LLM\RequestToLLMFailed;
use Cognesy\Instructor\Events\LLM\StreamedResponseReceived;
use Cognesy\Instructor\Exceptions\JSONParsingException;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\Result;
use Generator;

abstract class AbstractStreamedJsonHandler extends AbstractStreamedCallHandler
{
    use ValidatesPartialResponse;
    private bool $matchToExpectedFields = false;
    private bool $preventJsonSchema = false;

    protected string $responseText = '';
    protected string $responseJson = '';

    protected function processStream(Generator $stream) : Result {
        // process stream
        $functionCalls = [];
        $newFunctionCall = $this->newFunctionCall();
        $functionCalls[] = $newFunctionCall;

        /** @var PartialApiResponse $response */
        foreach($stream as $response){
            // receive data
            $this->events->dispatch(new StreamedResponseReceived($response));
            $this->lastResponse = $response;

            // skip if chunk empty
            $maybeArgumentChunk = $response->delta;
            if (empty($maybeArgumentChunk)) {
                continue;
            }

            // process response
            $this->responseText .= $maybeArgumentChunk;
            $this->responseJson = Json::findPartial($this->responseText);

            // fix this - change to Result based instead of exception based handling
            try {
                $this->validatePartialResponse($this->responseText, $this->responseModel, $this->preventJsonSchema, $this->matchToExpectedFields);
            } catch (JsonParsingException $e) {
                return Result::failure($e);
            }

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

    abstract protected function getStream() : Result;
}