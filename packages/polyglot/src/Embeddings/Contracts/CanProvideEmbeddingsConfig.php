<?php

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Utils\Config\Contracts\CanProvideConfig;

/**
 * @extends CanProvideConfig<EmbeddingsConfig>
 */
interface CanProvideEmbeddingsConfig extends CanProvideConfig
{
    public function getConfig(?string $preset = ''): EmbeddingsConfig;
}