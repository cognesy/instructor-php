<?php

namespace Cognesy\Instructor\Extras\Chat\Contracts;

use Cognesy\Instructor\Utils\Messages\Messages;

interface CanSummarizeMessages
{
    public function summarize(Messages $messages, int $tokenLimit) : string;
}