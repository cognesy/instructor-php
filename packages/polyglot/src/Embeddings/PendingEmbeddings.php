<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsResponseReceived;
use Cognesy\Utils\Json\Json;
use Psr\EventDispatcher\EventDispatcherInterface;

class PendingEmbeddings
{
    private readonly CanHandleVectorization $driver;
    private readonly EventDispatcherInterface $events;
    private readonly EmbeddingsRequest $request;

    private HttpResponse $httpResponse;
    private ?EmbeddingsResponse $response = null;

    public function __construct(
        EmbeddingsRequest $request,
        CanHandleVectorization $driver,
        EventDispatcherInterface $events,
    ) {
        $this->events = $events;
        $this->request = $request;
        $this->driver = $driver;
    }

    public function request() : EmbeddingsRequest {
        return $this->request;
    }

    public function get() : EmbeddingsResponse {
        if ($this->response === null) {
            $this->response = $this->makeResponse();
        }
        return $this->response;
    }

    public function makeResponse() : EmbeddingsResponse {
        $this->httpResponse = $this->driver->handle($this->request);
        $responseBody = $this->httpResponse->body();
        $data = Json::decode($responseBody) ?? [];
        $response = $this->driver->fromData($data);
        $this->events->dispatch(new EmbeddingsResponseReceived($response));
        return $response;
    }
}