<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Tests\Support;

use Closure;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;

final class FakeEmbeddingsDriver implements CanHandleVectorization
{
    /** @var EmbeddingsResponse[] */
    private array $responses;
    /** @var null|Closure(EmbeddingsRequest, self):EmbeddingsResponse */
    private ?Closure $onResponse;

    public int $handleCalls = 0;
    /** @var EmbeddingsRequest[] */
    public array $requests = [];

    /**
     * @param EmbeddingsResponse[] $responses
     * @param null|Closure(EmbeddingsRequest, self):EmbeddingsResponse $onResponse
     */
    public function __construct(
        array $responses = [],
        ?Closure $onResponse = null,
    ) {
        $this->responses = $responses;
        $this->onResponse = $onResponse;
    }

    public function handle(EmbeddingsRequest $request): EmbeddingsResponse {
        $this->handleCalls++;
        $this->requests[] = $request;
        return $this->resolveResponse($request);
    }

    private function resolveResponse(EmbeddingsRequest $request): EmbeddingsResponse
    {
        if ($this->onResponse !== null) {
            return ($this->onResponse)($request, $this);
        }

        if ($this->responses !== []) {
            /** @var EmbeddingsResponse $response */
            $response = array_shift($this->responses);
            return $response;
        }

        return new EmbeddingsResponse();
    }
}
