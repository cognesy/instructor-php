<?php

namespace Cognesy\Instructor\Extras\Chat\Pipelines;

use Cognesy\Instructor\Extras\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Instructor\Extras\Chat\Processors\MoveMessagesToBuffer;
use Cognesy\Instructor\Extras\Chat\Processors\SummarizeBuffer;
use Cognesy\Instructor\Extras\Chat\ScriptPipeline;
use Cognesy\Instructor\Extras\Chat\Utils\SummarizeMessages;

class ChatWithBufferAndSummary {
    public static function create(
        int $maxChatTokens = 1024,
        int $maxBufferTokens = 1024,
        int $maxSummaryTokens = 1024,
        CanSummarizeMessages $summarizer = null
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