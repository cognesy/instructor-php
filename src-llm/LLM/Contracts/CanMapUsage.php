<?php

namespace Cognesy\LLM\LLM\Contracts;

use Cognesy\LLM\LLM\Data\Usage;

interface CanMapUsage
{
    public function fromData(array $data): Usage;
}