<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Data;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

describe('AgentState execution buffer', function () {
    it('includes execution buffer in messagesForInference', function () {
        $state = AgentState::empty();
        $store = $state->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->setMessages(Messages::fromString('trace', 'tool'));
        $state = $state->withMessageStore($store);

        $messages = $state->messagesForInference();

        expect($messages->filter(fn(Message $message): bool => $message->isTool())->count())->toBe(1);
    });

    it('clears execution buffer during continuation reset', function () {
        $state = AgentState::empty();
        $store = $state->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->setMessages(Messages::fromString('trace', 'tool'));
        $state = $state->withMessageStore($store);

        $continued = $state->forContinuation();

        expect($continued->store()->section(AgentState::EXECUTION_BUFFER_SECTION)->isEmpty())->toBeTrue();
    });
});
