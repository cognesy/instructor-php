<?php

declare(strict_types=1);

namespace Examples\Retrieval\SupportReplyWithSources;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\PendingEmbeddings;
use Override;

/** Deliberately ranks ineligible policies first; these vectors do not measure semantic quality. */
final readonly class FixtureEmbeddings implements CanCreateEmbeddings, CanHandleVectorization
{
    public function __construct(private CanHandleEvents $events) {}

    #[Override]
    public function create(EmbeddingsRequest $request): PendingEmbeddings {
        return new PendingEmbeddings($request, $this, $this->events);
    }

    #[Override]
    public function handle(EmbeddingsRequest $request): EmbeddingsResponse {
        $vectors = [];
        foreach ($request->inputs() as $index => $text) {
            $vectors[] = new Vector($this->coordinates($text), $index);
        }

        return new EmbeddingsResponse($vectors);
    }

    /** @return list<float> */
    private function coordinates(string $text): array {
        return match (true) {
            str_starts_with($text, 'ARCHIVED') => [1.0, 0.0, 0.0],
            str_starts_with($text, 'ENTERPRISE') => [0.99, 0.01, 0.0],
            str_starts_with($text, 'STANDARD') => [0.95, 0.15, 0.0],
            str_starts_with($text, 'CANCEL') => [0.90, 0.30, 0.0],
            str_starts_with($text, 'PAYMENT') => [0.0, 1.0, 0.0],
            str_starts_with($text, 'ACCOUNT') => [0.0, 0.0, 1.0],
            default => [1.0, 0.0, 0.0],
        };
    }
}
