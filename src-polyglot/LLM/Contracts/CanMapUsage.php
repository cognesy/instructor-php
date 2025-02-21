<?php

namespace Cognesy\Polyglot\LLM\Contracts;

use Cognesy\Polyglot\LLM\Data\Usage;

interface CanMapUsage
{
    public function fromData(array $data): Usage;
}