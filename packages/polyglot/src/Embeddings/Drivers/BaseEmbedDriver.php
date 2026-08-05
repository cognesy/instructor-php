<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers;

use Cognesy\Events\Support\ListenerGate;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedRequestAdapter;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedResponseAdapter;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsFailed;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsRequested;
use Cognesy\Polyglot\Support\Redaction\RedactsHttpPayloads;
use Cognesy\Utils\Json\Json;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

class BaseEmbedDriver implements CanHandleVectorization
{
    use RedactsHttpPayloads;

    /**
     * Whether anything consumes each lifecycle event. Resolved once per driver,
     * mirroring BaseInferenceRequestDriver — EmbeddingsRequested carries a full
     * $request->toArray(), and the EmbeddingsFailed payloads do redaction work.
     * See ListenerGate: fail-open is contractual.
     */
    private readonly bool $emitRequested;
    private readonly bool $emitFailed;

    public function __construct(
        protected EmbeddingsConfig $config,
        protected CanSendHttpRequests $httpClient,
        protected EventDispatcherInterface $events,
        protected EmbedRequestAdapter $requestAdapter,
        protected EmbedResponseAdapter $responseAdapter
    ) {
        $this->emitRequested = ListenerGate::wants($events, EmbeddingsRequested::class);
        $this->emitFailed = ListenerGate::wants($events, EmbeddingsFailed::class);
    }

    #[\Override]
    public function handle(EmbeddingsRequest $request): EmbeddingsResponse {
        $clientRequest = $this->requestAdapter->toHttpClientRequest($request);
        if ($this->emitRequested) {
            $this->events->dispatch(new EmbeddingsRequested(['request' => $request->toArray()]));
        }

        $httpResponse = $this->makeHttpResponse($clientRequest);
        $data = Json::decode($httpResponse->body());
        if (!is_array($data)) {
            throw new RuntimeException('Failed to decode embeddings response data');
        }

        return $this->responseAdapter->fromResponse($data);
    }

    // INTERNAL //////////////////////////////////////////////////////////////

    protected function makeHttpResponse(HttpRequest $request): HttpResponse {
        try {
            $httpResponse = $this->httpClient->send($request)->get();
        } catch (Exception $e) {
            if ($this->emitFailed) {
                $this->events->dispatch(new EmbeddingsFailed([
                    'exception' => $this->redactedExceptionMessage($e, $request),
                    'request' => $this->redactedRequest($request),
                ]));
            }
            throw $e;
        }

        if ($httpResponse->statusCode() >= 400) {
            if ($this->emitFailed) {
                $this->events->dispatch(new EmbeddingsFailed([
                    'statusCode' => $httpResponse->statusCode(),
                    'headers' => $this->redactedHeaders($httpResponse->headers()),
                    'body' => '[REDACTED]',
                ]));
            }
            throw new HttpRequestException(
                message: 'HTTP request failed with status code ' . $httpResponse->statusCode(),
                request: $request,
                response: $httpResponse,
            );
        }
        return $httpResponse;
    }

}
