<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\CohereV1;

use Cognesy\Instructor\Features\LLM\Contracts\CanMapMessages;

class CohereV1MessageFormat implements CanMapMessages
{
    public function map(array $messages): array {
        return $messages;
    }
}