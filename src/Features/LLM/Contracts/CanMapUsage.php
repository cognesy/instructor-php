<?php

namespace Cognesy\Instructor\Features\LLM\Contracts;

use Cognesy\Instructor\Features\LLM\Data\Usage;

interface CanMapUsage
{
    public function fromData(array $data): Usage;
}