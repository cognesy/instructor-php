<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Summarization\Contracts;

use Cognesy\Messages\Messages;

interface CanSummarizeMessages
{
    public function summarize(Messages $messages, int $tokenLimit) : string;
}
