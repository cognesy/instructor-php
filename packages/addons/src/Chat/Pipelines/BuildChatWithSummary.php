<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Pipelines;

use Cognesy\Addons\Chat\Chat;
use Cognesy\Addons\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Addons\Chat\Processors\MoveMessagesToBuffer;
use Cognesy\Addons\Chat\Processors\SummarizeBuffer;
use Cognesy\Addons\Chat\Utils\SummarizeMessages;
use Cognesy\Messages\Script\Script;

final class BuildChatWithSummary
{
    public static function create(
        int $maxChatTokens = 2048,
        int $maxBufferTokens = 4096,
        int $maxSummaryTokens = 1024,
        ?CanSummarizeMessages $summarizer = null,
        ?string $model = null,
    ) : Chat {
        $script = (new Script())
            ->withSection('system')
            ->withSection('summary')
            ->withSection('buffer')
            ->withSection('main');

        $chat = new Chat();
        $chat->withState(new \Cognesy\Addons\Chat\Data\ChatState($script));
        $chat->withParticipants([
            new LLMParticipant(id: 'assistant', model: $model),
        ]);
        $chat->withScriptProcessors(
            new MoveMessagesToBuffer('main', 'buffer', $maxChatTokens),
            new SummarizeBuffer(
                sourceSection: 'buffer',
                targetSection: 'summary',
                maxBufferTokens: $maxBufferTokens,
                maxSummaryTokens: $maxSummaryTokens,
                summarizer: $summarizer ?? new SummarizeMessages()
            ),
        );
        return $chat;
    }
}

