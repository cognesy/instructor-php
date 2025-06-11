<?php

namespace Cognesy\Polyglot\Inference\Drivers\CohereV1;

use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;

class CohereV1MessageFormat implements CanMapMessages
{
    public function map(array $messages): array {
        return $messages;
    }
}