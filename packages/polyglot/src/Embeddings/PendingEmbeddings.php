<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Http\Data\HttpResponse;
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

    private ?HttpResponse $httpResponse = null;
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
        $data = Json::decode($this->httpResponse->body()) ?? [];
        $response = $this->driver->fromData($data);
        if ($response === null) {
            throw new \RuntimeException('Failed to create embeddings response from data');
        }
        $this->events->dispatch(new EmbeddingsResponseReceived($response));
        return $response;
    }
}