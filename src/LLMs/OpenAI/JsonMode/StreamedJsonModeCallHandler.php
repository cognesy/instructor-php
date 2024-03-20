<?php
namespace Cognesy\Instructor\LLMs\OpenAI\JsonMode;

use Cognesy\Instructor\Data\LLMResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\LLM\ChunkReceived;
use Cognesy\Instructor\Events\LLM\PartialJsonReceived;
use Cognesy\Instructor\Events\LLM\RequestSentToLLM;
use Cognesy\Instructor\Events\LLM\RequestToLLMFailed;
use Cognesy\Instructor\Events\LLM\StreamedResponseFinished;
use Cognesy\Instructor\Events\LLM\StreamedResponseReceived;
use Cognesy\Instructor\Exceptions\JSONParsingException;
use Cognesy\Instructor\LLMs\AbstractStreamedCallHandler;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\Result;
use Exception;
use OpenAI\Client;

class StreamedJsonModeCallHandler extends AbstractStreamedCallHandler
{
    private Client $client;
    private bool $matchToExpectedFields = false;
    private bool $preventJsonSchema = true;
    private string $responseText = '';

    public function __construct(
        EventDispatcher $events,
        Client $client,
        array $request,
        ResponseModel $responseModel,
    ) {
        $this->client = $client;
        $this->events = $events;
        $this->request = $request;
        $this->responseModel = $responseModel;
    }

    protected function getStream() : Result {
        try {
            $stream = $this->client->chat()->createStreamed($this->request);
        } catch (Exception $e) {
            return Result::failure($e);
        }
        return Result::success($stream);
    }

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
            $this->responseJson = Json::extractPartial($this->responseText);

            try {
                $this->preventJsonSchemaResponse();
                $this->detectNonMatchingJson();
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
            $this->finalizeFunctionCall($functionCalls, Json::extract($this->responseText));
        }
        return Result::success($functionCalls);
    }


    protected function getFinishReason(mixed $response) : string {
        return $response->choices[0]->finishReason ?? '';
    }

    private function getArgumentChunk($response) : string {
        return $response->choices[0]->delta->content ?? '';
    }

    private function preventJsonSchemaResponse() {
        if (
            $this->preventJsonSchema
            && $this->isJsonSchemaResponse($this->responseJson)
        ) {
            throw new JsonParsingException(
                message: 'You started responding with JSONSchema. Respond with JSON data instead.',
                json: $this->responseText,
            );
        }
    }

    private function isJsonSchemaResponse(string $jsonData) : bool {
        // ...detect JSONSchema response
        try {
            $decoded = json_decode($jsonData, true);
        } catch (Exception $e) {
            return false;
        }
        if (isset($decoded['type']) && $decoded['type'] === 'object') {
            return true;
        }
        return false;
    }

    private function detectNonMatchingJson() {
        if (
            $this->matchToExpectedFields
            && !$this->isMatchingResponseModel($this->responseJson, $this->responseModel)
        ) {
            throw new JsonParsingException(
                message: 'JSON does not match schema.',
                json: $this->responseText,
            );
        }
    }

    private function isMatchingResponseModel(
        string $jsonData,
        ResponseModel $responseModel
    ) : bool {
        // ...check for response model property names
        $propertyNames = isset($responseModel->schema->properties)
            ? array_keys($responseModel->schema->properties)
            : [];
        if (empty($propertyNames)) {
            return true;
        }
        // ...detect matching response model
        try {
            $decoded = json_decode($jsonData, true);
        } catch (Exception $e) {
            return false;
        }
        // Question: how to make this work while we're getting partially
        // retrieved field names
        $decodedKeys = array_filter(array_keys($decoded));
        if (empty($decodedKeys)) {
            return true;
        }
        return Arrays::isSubset($decodedKeys, $propertyNames);
    }
}