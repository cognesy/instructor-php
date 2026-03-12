<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsUsage;

interface CanMapUsage
{
    public function fromData(array $data): EmbeddingsUsage;
}
