<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedRequestAdapter;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedResponseAdapter;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsFailed;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsRequested;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;
use RuntimeException;

class BaseEmbedDriver implements CanHandleVectorization
{
    protected EmbeddingsConfig $config;
    protected HttpClient $httpClient;
    protected EventDispatcherInterface $events;

    protected EmbedRequestAdapter $requestAdapter;
    protected EmbedResponseAdapter $responseAdapter;

    public function handle(EmbeddingsRequest $request): HttpResponse {
        $clientRequest = $this->requestAdapter->toHttpClientRequest($request);
        $this->events->dispatch(new EmbeddingsRequested(['request' => $request->toArray()]));
        return $this->makeHttpResponse($clientRequest);
    }

    public function fromData(array $data): ?EmbeddingsResponse {
        return $this->responseAdapter->fromResponse($data);
    }

    // INTERNAL //////////////////////////////////////////////////////////////

    protected function makeHttpResponse(HttpRequest $request): HttpResponse {
        try {
            $httpResponse = $this->httpClient->withRequest($request)->get();
        } catch (Exception $e) {
            $this->events->dispatch(new EmbeddingsFailed([
                'exception' => $e->getMessage(),
                'request' => $request->toArray(),
            ]));
            throw $e;
        }

        if ($httpResponse->statusCode() >= 400) {
            $this->events->dispatch(new EmbeddingsFailed([
                'statusCode' => $httpResponse->statusCode(),
                'headers' => $httpResponse->headers(),
                'body' => $httpResponse->body(),
            ]));
            throw new RuntimeException('HTTP request failed with status code ' . $httpResponse->statusCode());
        }
        return $httpResponse;
    }
}
