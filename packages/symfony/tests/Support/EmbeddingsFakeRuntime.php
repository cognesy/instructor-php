<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Tests\Support;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsUsage;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\PendingEmbeddings;

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

    public function handle(EmbeddingsRequest $request): EmbeddingsResponse
    {
        $vectors = array_map(
            fn (string $input, int $index): Vector => new Vector(
                values: $this->vectorFor($input),
                id: $index,
            ),
            $request->inputs(),
            array_keys($request->inputs()),
        );

        return new EmbeddingsResponse(
            vectors: $vectors,
            usage: EmbeddingsUsage::fromArray(['input' => count($request->inputs())]),
        );
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
