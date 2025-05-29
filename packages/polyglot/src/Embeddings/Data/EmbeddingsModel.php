<?php

namespace Cognesy\Polyglot\Embeddings\Data;

class EmbeddingsModel
{
    public function __construct(
        protected string $id,
        protected string $name,
    ) {}
}