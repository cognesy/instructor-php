<?php

namespace Cognesy\Instructor\Core\Response;

use Cognesy\Instructor\Contracts\CanHandlePartialResponse;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Core\PartialsGenerator;
use Cognesy\Instructor\Core\SequenceableHandler;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\RequestHandler\PartialResponseGenerated;
use Cognesy\Instructor\Events\RequestHandler\PartialResponseGenerationFailed;
use Cognesy\Instructor\Events\RequestHandler\SequenceUpdated;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\Chain;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\Result;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class NewPartialResponseHandler implements CanHandlePartialResponse
{
    private string $previousHash = '';
    private ?Sequenceable $lastPartialResponse;
    private int $previousSequenceLength = 1;
    private SequenceableHandler $sequenceableHandler;

    public function __construct(
        private ResponseDeserializer $responseDeserializer,
        private ResponseTransformer $responseTransformer,
        private EventDispatcher $events,
        private PartialsGenerator $partialsGenerator,
    ) {
        $this->sequenceableHandler = new SequenceableHandler($events);
    }

    public function handlePartialResponse(
        string $partialJsonData,
        ResponseModel $responseModel
    ) : void {
        $jsonData = Json::fix($partialJsonData);
        $result = $this->toPartialResponse($jsonData, $responseModel);
        if ($result->isFailure()) {
            $errors = Arrays::toArray($result->error());
            $this->events->dispatch(new PartialResponseGenerationFailed($errors));
            return;
        }

        // proceed if converting to object was successful
        $partialResponse = clone $result->unwrap();
        $currentHash = hash('xxh3', Json::encode($partialResponse));
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
        if (empty($this->lastPartialResponse)) {
            return;
        }
        if (!($this->lastPartialResponse instanceof Sequenceable)) {
            return;
        }
        //$this->events->dispatch(new SequenceUpdated($this->lastPartialResponse));
        $this->processSequenceable($this->lastPartialResponse);
    }

    ///////////////////////////////////////////////////////////////////////////////////

    protected function toPartialResponse(string $partialJsonData, ResponseModel $responseModel): Result
    {
        return Chain::from(fn() => Json::fix($partialJsonData))
            ->through(fn($jsonData) => $this->responseDeserializer->deserialize($jsonData, $responseModel))
            ->through(fn($object) => $this->responseTransformer->transform($object))
            ->result();
    }

    protected function processSequenceable(Sequenceable $partialResponse) : void {
        $currentLength = count($partialResponse);
        if ($currentLength <= $this->previousSequenceLength) {
            return;
        }
        $this->previousSequenceLength = $currentLength;
        $this->events->dispatch(new SequenceUpdated($partialResponse));
    }
}