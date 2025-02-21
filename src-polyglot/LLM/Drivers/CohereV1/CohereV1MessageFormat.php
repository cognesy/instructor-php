<?php

namespace Cognesy\Polyglot\LLM\Drivers\CohereV1;

use Cognesy\Polyglot\LLM\Contracts\CanMapMessages;

class CohereV1MessageFormat implements CanMapMessages
{
    public function map(array $messages): array {
        return $messages;
    }
}