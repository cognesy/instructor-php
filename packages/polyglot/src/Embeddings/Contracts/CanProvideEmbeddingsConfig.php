<?php

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Utils\Config\Contracts\CanProvideConfig;

/**
 * @extends CanProvideConfig<\Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig>
 */
interface CanProvideEmbeddingsConfig extends CanProvideConfig
{
    public function getConfig(?string $preset = ''): EmbeddingsConfig;
}