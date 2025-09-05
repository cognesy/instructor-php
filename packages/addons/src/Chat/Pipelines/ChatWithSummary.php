<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Pipelines;

use Cognesy\Addons\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Addons\Chat\Processors\MoveMessagesToBuffer;
use Cognesy\Addons\Chat\Processors\SummarizeBuffer;
use Cognesy\Addons\Chat\ScriptPipeline;
use Cognesy\Addons\Chat\Utils\SummarizeMessages;

/**
 * @deprecated Use Pipelines\\BuildChatWithSummary which returns a configured
 *             Cognesy\\Addons\\Chat\\Chat instance. This legacy builder will
 *             be removed in a future release.
 */
class ChatWithSummary
{
    public static function create(
        int $maxChatTokens = 1024,
        int $maxBufferTokens = 1024,
        int $maxSummaryTokens = 1024,
        ?CanSummarizeMessages $summarizer = null
    ) : ScriptPipeline {
        $sections = ['main', 'buffer', 'summary'];
        $processors = [
            new MoveMessagesToBuffer(
                sourceSection: 'main',
                targetSection: 'buffer',
                maxTokens: $maxChatTokens
            ),
            new SummarizeBuffer(
                sourceSection: 'buffer',
                targetSection: 'summary',
                maxBufferTokens: $maxBufferTokens,
                maxSummaryTokens: $maxSummaryTokens,
                summarizer: $summarizer ?? new SummarizeMessages()
            )
        ];
        return new ScriptPipeline($sections, $processors);
    }
}
