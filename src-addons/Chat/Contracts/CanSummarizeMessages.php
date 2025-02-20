<?php

namespace Cognesy\Addons\Chat\Contracts;

use Cognesy\Utils\Messages\Messages;

interface CanSummarizeMessages
{
    public function summarize(Messages $messages, int $tokenLimit) : string;
}