<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Data;

use Cognesy\Agents\Context\AgentContext;
use Cognesy\Agents\Context\Compilers\SelectedSections;
use Cognesy\Agents\Context\ContextSections;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;
use Cognesy\Utils\Metadata;

it('compiles inference messages in section order', function () {
    $store = MessageStore::fromSections(
        new Section(ContextSections::SUMMARY, Messages::fromString('SUMMARY', 'system')),
        new Section(ContextSections::BUFFER, Messages::fromString('BUFFER', 'user')),
        new Section(ContextSections::DEFAULT, Messages::fromString('RECENT', 'user')),
    );

    $state = new AgentState(context: new AgentContext(store: $store));

    $messages = (new SelectedSections([
        ContextSections::SUMMARY,
        ContextSections::BUFFER,
        ContextSections::DEFAULT,
    ]))->compile($state);

    expect(trim($messages->toString()))->toBe("SUMMARY\nBUFFER\nRECENT");
});

it('serializes and restores context data', function () {
    $store = MessageStore::fromSections(
        new Section('messages', Messages::fromString('Hi', 'user')),
    );
    $context = new AgentContext(
        store: $store,
        metadata: new Metadata(['locale' => 'en']),
        systemPrompt: 'You are a helpful assistant.',
    );

    $restored = AgentContext::fromArray($context->toArray());

    expect($restored->metadata()->toArray())->toBe(['locale' => 'en'])
        ->and($restored->store()->sections()->names())->toBe(['messages'])
        ->and($restored->systemPrompt())->toBe('You are a helpful assistant.');
});
