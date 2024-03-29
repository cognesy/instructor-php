<?php

namespace Cognesy\Instructor\Core\Response;

use Cognesy\Instructor\Contracts\CanHandlePartialResponse;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\RequestHandler\PartialResponseGenerated;
use Cognesy\Instructor\Events\RequestHandler\PartialResponseGenerationFailed;
use Cognesy\Instructor\Events\RequestHandler\SequenceUpdated;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\JsonParser;
use Cognesy\Instructor\Utils\Result;

class PartialResponseHandler implements CanHandlePartialResponse
{
    private string $previousHash = '';
    private ?Sequenceable $lastPartialResponse;
    private int $previousSequenceLength = 1;

    public function __construct(
        private ResponseDeserializer $responseDeserializer,
        private ResponseTransformer $responseTransformer,
        private EventDispatcher $events,
    ) {}

    public function handlePartialResponse(
        string $partialJsonData,
        ResponseModel $responseModel
    ) : void {
        $jsonData = (new JsonParser)->fix($partialJsonData);
        $result = $this->toPartialResponse($jsonData, $responseModel);
        if ($result->isFailure()) {
            $errors = Arrays::toArray($result->error());
            $this->events->dispatch(new PartialResponseGenerationFailed($errors));
            return;
        }

        // proceed if converting to object was successful
        $partialResponse = clone $result->unwrap();
        $currentHash = hash('xxh3', json_encode($partialResponse));
        if ($this->previousHash != $currentHash) {
            // send partial response to listener only if new tokens changed resulting response object
            $this->events->dispatch(new PartialResponseGenerated($partialResponse));
            if (($partialResponse instanceof Sequenceable)) {
                $this->processSequenceable($partialResponse);
                $this->lastPartialResponse = clone $partialResponse;
            }
            $this->previousHash = $currentHash;
        }
    }

    public function resetPartialResponse() : void {
        $this->previousHash = '';
        $this->lastPartialResponse = null;
        $this->previousSequenceLength = 1;
    }

    public function finalizePartialResponse(ResponseModel $responseModel) : void {
        if (!isset($this->lastPartialResponse)) {
            return;
        }
        if (!($this->lastPartialResponse instanceof Sequenceable)) {
            return;
        }
        $this->events->dispatch(new SequenceUpdated($this->lastPartialResponse));
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////

    public function toPartialResponse(string $jsonData, ResponseModel $responseModel): Result
    {
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

    protected function processSequenceable(Sequenceable $partialResponse) : void {
        $currentLength = count($partialResponse);
        if ($currentLength <= $this->previousSequenceLength) {
            return;
        }
        $this->previousSequenceLength = $currentLength;
        $this->events->dispatch(new SequenceUpdated($this->lastPartialResponse));
    }
}