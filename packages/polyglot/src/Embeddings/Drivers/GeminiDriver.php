<?php
namespace Cognesy\Polyglot\Embeddings\Drivers;

use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\EmbeddingsResponse;
use Cognesy\Polyglot\LLM\Data\Usage;
use Psr\EventDispatcher\EventDispatcherInterface;

class GeminiDriver implements CanHandleVectorization
{
    private int $inputCharacters = 0;

    protected EmbeddingsConfig         $config;
    protected HttpClient      $httpClient;
    protected EventDispatcherInterface $events;

    public function __construct(
        EmbeddingsConfig         $config,
        HttpClient      $httpClient,
        EventDispatcherInterface $events,
    ) {
        $this->events = $events;
        $this->httpClient = $httpClient;
        $this->config = $config;
    }

    public function handle(EmbeddingsRequest $request): EmbeddingsResponse {
        $input = $request->inputs();
        $this->inputCharacters = $this->countCharacters($input);
        $options = $request->options();
        $httpRequest = new HttpClientRequest(
            url: $this->getEndpointUrl(),
            method: 'POST',
            headers: $this->getRequestHeaders(),
            body: $this->getRequestBody($input, $options),
            options: [],
        );
        $response = $this->httpClient->handle($httpRequest);
        return $this->toResponse(json_decode($response->body(), true));
    }

    // INTERNAL /////////////////////////////////////////////////

    protected function getEndpointUrl(): string {
        return str_replace(
            "{model}",
            $this->config->model,
            "{$this->config->apiUrl}{$this->config->endpoint}?key={$this->config->apiKey}"
        );
    }

    protected function getRequestHeaders(): array {
        return [
            'Content-Type' => 'application/json',
        ];
    }

    protected function getRequestBody(array $input, array $options) : array {
        return array_merge([
            'requests' => array_map(
                fn($item) => [
                    'model' => $this->config->model,
                    'content' => ['parts' => [['text' => $item]]]
                ],
                $input
            ),
        ], $options);
    }

    protected function toResponse(array $response) : EmbeddingsResponse {
        $vectors = [];
        foreach ($response['embeddings'] as $key => $item) {
            $vectors[] = new Vector(values: $item['values'], id: $key);
        }
        return new EmbeddingsResponse(
            vectors: $vectors,
            usage: $this->makeUsage($response),
        );
    }

    protected function countCharacters(array $input) : int {
        return array_sum(array_map(fn($item) => strlen($item), $input));
    }

    protected function makeUsage(array $response) : Usage {
        return new Usage(
            inputTokens: $this->inputCharacters,
            outputTokens: 0,
        );
    }
}
