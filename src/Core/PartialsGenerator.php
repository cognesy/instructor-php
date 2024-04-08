<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Data\Responses\PartialApiResponse;
use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Core\ApiClient\ValidatesPartialResponse;
use Cognesy\Instructor\Core\Response\ResponseDeserializer;
use Cognesy\Instructor\Core\Response\ResponseTransformer;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\ToolCall;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\LLM\ChunkReceived;
use Cognesy\Instructor\Events\LLM\PartialJsonReceived;
use Cognesy\Instructor\Events\LLM\RequestSentToLLM;
use Cognesy\Instructor\Events\LLM\RequestToLLMFailed;
use Cognesy\Instructor\Events\LLM\StreamedResponseReceived;
use Cognesy\Instructor\Events\LLM\StreamedToolCallCompleted;
use Cognesy\Instructor\Events\LLM\StreamedToolCallStarted;
use Cognesy\Instructor\Events\LLM\StreamedToolCallUpdated;
use Cognesy\Instructor\Events\RequestHandler\PartialResponseGenerated;
use Cognesy\Instructor\Events\RequestHandler\PartialResponseGenerationFailed;
use Cognesy\Instructor\Events\RequestHandler\SequenceUpdated;
use Cognesy\Instructor\Exceptions\JsonParsingException;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\JsonParser;
use Cognesy\Instructor\Utils\Result;
use Exception;
use Generator;

class PartialsGenerator implements CanGeneratePartials
{
    use ValidatesPartialResponse;

    // state
    private PartialApiResponse $lastPartialResponse;
    private string $responseJson = '';
    private string $responseText = '';
    // sequenceable support state
    private string $previousHash = '';
    private ?Sequenceable $lastPartialSequence;
    private int $previousSequenceLength = 1;
    // options
    private bool $matchToExpectedFields = false;
    private bool $preventJsonSchema = false;
    private array $toolCalls = [];

    public function __construct(
        private EventDispatcher $events,
        private ResponseDeserializer $responseDeserializer,
        private ResponseTransformer $responseTransformer,
        private RequestBuilder $requestBuilder,
    ) {}

    public function getPartialResponses(Request $request, ResponseModel $responseModel, array $messages = []) : Generator {
        // get function caller instance
        /** @var ApiClient $apiCallRequest */
        $apiCallRequest = $this->requestBuilder->makeClientRequest(
            $messages, $responseModel, $request->model, $request->options, $request->mode
        );

        $this->toolCalls[] = $this->newToolCall($responseModel);
        try {
            $this->events->dispatch(new RequestSentToLLM($apiCallRequest->getRequest()));
            $stream = $apiCallRequest->stream();
        } catch(Exception $e) {
            $this->events->dispatch(new RequestToLLMFailed([], $e->getMessage()));
            throw new Exception($e->getMessage());
        }
        $generator = $this->partialObjectsGenerator($stream, $responseModel);
        yield from $generator;

        // check if there are any toolCalls
        if (count($this->toolCalls) === 0) {
            throw new Exception('No tool calls found in the response');
        }
        // finalize last function call
        if (count($this->toolCalls) > 0) {
            $this->finalizeToolCall($this->toolCalls, Json::find($this->responseText));
        }
    }

    public function partialObjectsGenerator(Generator $stream, ResponseModel $responseModel) : Iterable {
        /** @var PartialApiResponse $partialResponse */
        foreach($stream as $partialResponse){
            // receive data
            $this->events->dispatch(new StreamedResponseReceived($partialResponse));
            // store for finalization when we leave the loop
            $this->lastPartialResponse = $partialResponse;
            // situation 1: new function call
            $maybeFunctionName = $partialResponse->functionName;
            // create next FC only if JSON buffer is not empty (which is the case for 1st iteration)
            if ($maybeFunctionName && $this->responseJson) {
                $this->finalizeToolCall($this->toolCalls, $this->responseJson);
                $this->responseJson = ''; // reset json buffer
            }
            // situation 2: new delta
            // skip if no new delta
            $maybeArgumentChunk = $partialResponse->delta;
            if (empty($maybeArgumentChunk)) {
                continue;
            }
            $this->events->dispatch(new ChunkReceived($maybeArgumentChunk));
            $this->responseText .= $maybeArgumentChunk;
            $this->responseJson = Json::findPartial($this->responseText);
            if (empty($this->responseJson)) {
                continue;
            }
            $result = $this->handleDelta($this->responseJson, $responseModel);
            if (is_null($result) || $result->isFailure()) {
                continue;
            }
            $this->events->dispatch(new PartialJsonReceived($this->responseJson));
            yield $result->unwrap();
        }
    }

    protected function handleDelta(string $partialJson, ResponseModel $responseModel) : ?Result {
        $result = $this->validatePartialResponse($partialJson, $responseModel, $this->preventJsonSchema, $this->matchToExpectedFields);
        if ($result->isFailure()) {
            throw new JsonParsingException($result->error(), $partialJson);
        }
        $this->events->dispatch(new PartialJsonReceived($partialJson));
        $this->updateToolCall($this->toolCalls, $partialJson);
        $result = $this->tryGetPartialObject($partialJson, $responseModel);
        if ($result->isFailure()) {
            return $result;
        }
        $partialObject = $result->unwrap();
        // we only want to send partial response if it's different from the previous one
        $currentHash = hash('xxh3', Json::encode($partialObject));
        if ($this->previousHash != $currentHash) {
            // send partial response to listener only if new tokens changed resulting response object
            $this->events->dispatch(new PartialResponseGenerated($partialObject));
            if (($partialObject instanceof Sequenceable)) {
                $this->emitNewSequenceable($partialObject);
                $this->lastPartialSequence = clone $partialObject;
            }
            $this->previousHash = $currentHash;
            return $result;
        }
        return null;
    }

    protected function tryGetPartialObject(
        string $partialJsonData,
        ResponseModel $responseModel,
    ) : Result {
        $jsonData = (new JsonParser)->fix($partialJsonData);
        $result = $this->toPartialObject($jsonData, $responseModel);
        if ($result->isFailure()) {
            $errors = Arrays::toArray($result->error());
            $this->events->dispatch(new PartialResponseGenerationFailed($errors));
            return $result;
        }
        // proceed if converting to object was successful
        $partialObject = clone $result->unwrap();
        return Result::success($partialObject);
    }

    protected function toPartialObject(string $jsonData, ResponseModel $responseModel): Result {
        // ...deserialize
        $result = $this->responseDeserializer->deserialize($jsonData, $responseModel);
        if ($result->isFailure()) {
            return $result;
        }
        $object = $result->unwrap();
        // ...transform
        $result = $this->responseTransformer->transform($object);
        if ($result->isFailure()) {
            return $result;
        }
        return $result;
    }

    protected function emitNewSequenceable(Sequenceable $partialResponse) : void {
        $currentLength = count($partialResponse);
        if ($currentLength <= $this->previousSequenceLength) {
            return;
        }
        $this->previousSequenceLength = $currentLength;
        $this->events->dispatch(new SequenceUpdated($this->lastPartialSequence));
    }

    protected function finalizePartialSequence() : void {
        if (!isset($this->lastPartialSequence)) {
            return;
        }
        if (!($this->lastPartialSequence instanceof Sequenceable)) {
            return;
        }
        $this->events->dispatch(new SequenceUpdated($this->lastPartialSequence));
    }

    public function resetPartialSequence() : void {
        $this->previousHash = '';
        $this->lastPartialSequence = null;
        $this->previousSequenceLength = 1;
    }

    public function getApiResponse() {
        return new ApiResponse(
            content: $this->responseText,
            responseData: $this->lastPartialResponse->responseData ?? [],
            finishReason: $this->lastPartialResponse->finishReason ?? '',
            toolCalls: $this->toolCalls,
        );
    }

    protected function newToolCall(ResponseModel $responseModel, PartialApiResponse $response = null) : ToolCall {
        $toolName = $response->functionName ?? $responseModel->functionName;
        $newToolCall = new ToolCall(
            name: $toolName,
            args: ''
        );
        $this->events->dispatch(new StreamedToolCallStarted($newToolCall));
        return $newToolCall;
    }

    protected function updateToolCall(
        array  $toolCalls,
        string $responseJson,
    ) : void {
        /** @var \Cognesy\Instructor\Data\ToolCall $currentToolCall */
        $currentToolCall = $toolCalls[count($toolCalls) - 1];
        $currentToolCall->args = $responseJson;
        $this->events->dispatch(new StreamedToolCallUpdated($currentToolCall));
    }

    protected function finalizeToolCall(array $toolCalls, string $responseJson) : void {
        /** @var \Cognesy\Instructor\Data\ToolCall $currentToolCall */
        $currentToolCall = $toolCalls[count($toolCalls) - 1];
        $currentToolCall->args = $responseJson;
        $this->events->dispatch(new StreamedToolCallCompleted($currentToolCall));
        $this->finalizePartialSequence();
    }
}