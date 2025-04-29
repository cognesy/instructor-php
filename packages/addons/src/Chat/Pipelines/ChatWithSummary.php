<?php

namespace Cognesy\Addons\Chat\Pipelines;

use Cognesy\Addons\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Addons\Chat\Processors\MoveMessagesToBuffer;
use Cognesy\Addons\Chat\Processors\SummarizeBuffer;
use Cognesy\Addons\Chat\ScriptPipeline;
use Cognesy\Addons\Chat\Utils\SummarizeMessages;

class ChatWithSummary {
    public static function create(
        int $maxChatTokens = 1024,
        int $maxBufferTokens = 1024,
        int $maxSummaryTokens = 1024,
        ?CanSummarizeMessages $summarizer = null
    ) : ScriptPipeline {
        $sections = ['main', 'buffer', 'summary'];
        $processors = [
            new MoveMessagesToBuffer('main', 'buffer', $maxChatTokens),
            new SummarizeBuffer(
                'buffer',
                'summary',
                $maxBufferTokens,
                $maxSummaryTokens,
                $summarizer ?? new SummarizeMessages()
            )
        ];
        return new ScriptPipeline($sections, $processors);
    }
}