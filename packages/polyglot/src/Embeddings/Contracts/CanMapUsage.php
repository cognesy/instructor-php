<?php

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\LLM\Data\Usage;

interface CanMapUsage
{
    public function fromData(array $data): Usage;
}