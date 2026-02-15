<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Psr\EventDispatcher\EventDispatcherInterface;

final class EmbeddingsRuntime implements CanCreateEmbeddings
{
    public function __construct(
        private readonly CanHandleVectorization $driver,
        private readonly EventDispatcherInterface $events,
    ) {}

    #[\Override]
    public function create(EmbeddingsRequest $request): PendingEmbeddings {
        return new PendingEmbeddings(
            request: $request,
            driver: $this->driver,
            events: $this->events,
        );
    }
}

