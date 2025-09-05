<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Pipelines;

use Cognesy\Addons\Chat\Chat;
use Cognesy\Addons\Chat\Participants\HumanParticipant;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Addons\Chat\Selectors\RoundRobinSelector;
use Cognesy\Messages\Script\Script;

final class ChatQuickstart
{
    public static function create(
        ?string $model = null,
        int $maxChatTokens = 2048,
        int $maxBufferTokens = 4096,
        int $maxSummaryTokens = 1024,
    ) : Chat {
        $script = (new Script())
            ->withSection('system')
            ->withSection('summary')
            ->withSection('buffer')
            ->withSection('main');

        $chat = new Chat();
        $chat->withState(new \Cognesy\Addons\Chat\Data\ChatState($script));
        $chat->withSelector(new RoundRobinSelector());
        $chat->withParticipants([
            new HumanParticipant(id: 'user'),
            new LLMParticipant(id: 'assistant', model: $model),
        ]);
        // Script processors (buffer/summarize) can be attached by caller via withScriptProcessors
        return $chat;
    }
}

