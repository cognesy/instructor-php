<?php

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Inference\Data\Usage;

interface CanMapUsage
{
    public function fromData(array $data): Usage;
}