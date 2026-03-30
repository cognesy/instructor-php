<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Tests\Support;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsUsage;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\PendingEmbeddings;
use JsonException;

final class EmbeddingsFakeRuntime implements CanCreateEmbeddings, CanHandleVectorization
{
    /** @var list<EmbeddingsRequest> */
    private array $recorded = [];

    /**
     * @param array<string, list<float>> $responses
     * @param list<float> $defaultVector
     */
    public function __construct(
        private readonly array $responses = [],
        private readonly array $defaultVector = [0.1, 0.2, 0.3],
        private readonly EventDispatcher $events = new EventDispatcher('symfony-embeddings-fake'),
    ) {}

    /**
     * @param array<string, list<float>> $responses
     */
    public static function fromVectors(array $responses, array $defaultVector = [0.1, 0.2, 0.3]): self
    {
        return new self(responses: $responses, defaultVector: $defaultVector);
    }

    /** @return list<EmbeddingsRequest> */
    public function recorded(): array
    {
        return $this->recorded;
    }

    public function create(EmbeddingsRequest $request): PendingEmbeddings
    {
        $this->recorded[] = $request;

        return new PendingEmbeddings(
            request: $request,
            driver: $this,
            events: $this->events,
        );
    }

    public function handle(EmbeddingsRequest $request): HttpResponse
    {
        return HttpResponse::sync(
            statusCode: 200,
            headers: [],
            body: $this->payloadFor($request),
        );
    }

    public function fromData(array $data): ?EmbeddingsResponse
    {
        $vectors = array_map(
            static fn (array $item): Vector => new Vector(
                values: array_map(static fn (mixed $value): float => (float) $value, $item['values'] ?? []),
                id: $item['id'] ?? 0,
            ),
            $data['vectors'] ?? [],
        );

        return new EmbeddingsResponse(
            vectors: $vectors,
            usage: EmbeddingsUsage::fromArray($data['usage'] ?? []),
        );
    }

    private function payloadFor(EmbeddingsRequest $request): string
    {
        $payload = [
            'vectors' => array_map(
                fn (string $input, int $index): array => [
                    'id' => $index,
                    'values' => $this->vectorFor($input),
                ],
                $request->inputs(),
                array_keys($request->inputs()),
            ),
            'usage' => [
                'input' => count($request->inputs()),
            ],
        ];

        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Failed to encode fake embeddings response.', 0, $exception);
        }
    }

    /** @return list<float> */
    private function vectorFor(string $input): array
    {
        if (array_key_exists($input, $this->responses)) {
            return $this->responses[$input];
        }

        foreach ($this->responses as $pattern => $vector) {
            if (str_contains($input, $pattern)) {
                return $vector;
            }
        }

        return $this->defaultVector;
    }
}
