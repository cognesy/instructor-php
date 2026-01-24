<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Summarization\Contracts;

use Cognesy\Messages\Messages;

interface CanSummarizeMessages
{
    public function summarize(Messages $messages, int $tokenLimit) : string;
}
