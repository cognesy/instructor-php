<?php

namespace Cognesy\Addons\Chat\Contracts;

use Cognesy\Messages\Messages;

interface CanSummarizeMessages
{
    public function summarize(Messages $messages, int $tokenLimit) : string;
}