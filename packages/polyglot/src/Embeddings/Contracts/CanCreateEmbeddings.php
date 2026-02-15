<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\PendingEmbeddings;

interface CanCreateEmbeddings
{
    public function create(EmbeddingsRequest $request): PendingEmbeddings;
}
