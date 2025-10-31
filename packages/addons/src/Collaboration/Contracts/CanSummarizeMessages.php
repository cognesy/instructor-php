<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Contracts;

use Cognesy\Messages\Messages;

interface CanSummarizeMessages
{
    public function summarize(Messages $messages, int $tokenLimit) : string;
}