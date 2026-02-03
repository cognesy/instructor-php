<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Data;

use Cognesy\Agents\Core\Context\AgentContext;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;
use Cognesy\Utils\Metadata;

it('compiles inference messages in section order', function () {
    $store = MessageStore::fromSections(
        new Section(AgentContext::SUMMARY_SECTION, Messages::fromString('SUMMARY', 'system')),
        new Section(AgentContext::BUFFER_SECTION, Messages::fromString('BUFFER', 'user')),
        new Section(AgentContext::DEFAULT_SECTION, Messages::fromString('RECENT', 'user')),
        new Section(AgentContext::EXECUTION_BUFFER_SECTION, Messages::fromString('TRACE', 'tool')),
    );

    $context = new AgentContext(store: $store);

    expect(trim($context->messagesForInference()->toString()))->toBe("SUMMARY\nBUFFER\nRECENT\nTRACE");
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
