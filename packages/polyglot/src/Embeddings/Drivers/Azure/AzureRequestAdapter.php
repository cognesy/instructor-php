<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\Azure;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedRequestAdapter;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;

class AzureRequestAdapter implements EmbedRequestAdapter
{
    public function __construct(
        protected EmbeddingsConfig $config,
        protected CanMapRequestBody $bodyFormat,
    ) {}

    #[\Override]
    public function toHttpClientRequest(EmbeddingsRequest $request): HttpRequest {
         return new HttpRequest(
            url: $this->getEndpointUrl(),
            method: 'POST',
            headers: $this->getRequestHeaders(),
            body: $this->bodyFormat->toRequestBody($request),
            options: [],
        );
    }

    // INTERNAL /////////////////////////////////////////////

    protected function getEndpointUrl(): string {
        return str_replace(
                search: array_map(fn($key) => "{".$key."}", array_keys($this->config->metadata)),
                replace: array_values($this->config->metadata),
                subject: "{$this->config->apiUrl}{$this->config->endpoint}"
            ) . $this->getUrlParams();
    }

    protected function getUrlParams(): string {
        $params = array_filter([
            'api-version' => $this->config->metadata['apiVersion'] ?? '',
        ]);
        if (!empty($params)) {
            return '?' . http_build_query($params);
        }
        return '';
    }

    protected function getRequestHeaders(): array {
        return [
            'Api-Key' => $this->config->apiKey,
            'Content-Type' => 'application/json; charset=utf-8',
        ];
    }
}