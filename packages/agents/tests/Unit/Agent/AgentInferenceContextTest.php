<?php declare(strict_types=1);

use Cognesy\Agents\Context\AgentContext;
use Cognesy\Agents\Context\Compilers\SelectedSections;
use Cognesy\Agents\Context\ContextSections;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;

it('compiles summary buffer and messages for inference in order', function () {
    $store = MessageStore::fromSections(
        new Section('summary', Messages::fromString('SUMMARY', 'system')),
        new Section('buffer', Messages::fromString('BUFFER', 'user')),
        new Section('messages', Messages::fromString('RECENT', 'user')),
    );
    $state = new AgentState(context: new AgentContext(store: $store));

    $messages = (new SelectedSections([
        ContextSections::SUMMARY,
        ContextSections::BUFFER,
        ContextSections::DEFAULT,
    ]))->compile($state);

    expect(trim($messages->toString()))->toBe("SUMMARY\nBUFFER\nRECENT");
});
