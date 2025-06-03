<?php

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;

interface CanProvideEmbeddingsConfig
{
    public function getConfig(?string $preset = ''): EmbeddingsConfig;
}