<?php

namespace Cognesy\LLM\LLM\Drivers\CohereV1;

use Cognesy\LLM\LLM\Contracts\CanMapMessages;

class CohereV1MessageFormat implements CanMapMessages
{
    public function map(array $messages): array {
        return $messages;
    }
}