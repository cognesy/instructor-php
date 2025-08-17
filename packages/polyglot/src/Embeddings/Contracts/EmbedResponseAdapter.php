<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;

interface EmbedResponseAdapter
{
    public function fromResponse(array $data): EmbeddingsResponse;
}