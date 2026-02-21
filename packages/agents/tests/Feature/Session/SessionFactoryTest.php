<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Session;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\SessionFactory;
use Cognesy\Agents\Session\SessionStatus;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;

it('creates session with correct header from definition', function () {
    $factory = new SessionFactory(new DefinitionStateFactory());
    $definition = new AgentDefinition(
        name: 'test-agent',
        description: 'A test agent',
        systemPrompt: 'You are a test agent.',
        label: 'Test Agent',
    );

    $session = $factory->create($definition);

    expect($session->sessionId())->toBeString()->not->toBeEmpty();
    expect($session->status())->toBe(SessionStatus::Active);
    expect($session->version())->toBe(0);
    expect($session->info()->agentName())->toBe('test-agent');
    expect($session->info()->agentLabel())->toBe('Test Agent');
    expect($session->info()->parentId())->toBeNull();
});

it('creates session with state from state factory', function () {
    $factory = new SessionFactory(new DefinitionStateFactory());
    $definition = new AgentDefinition(
        name: 'agent',
        description: 'test',
        systemPrompt: 'Custom prompt',
    );

    $session = $factory->create($definition);

    expect($session->state()->context()->systemPrompt())->toBe('Custom prompt');
});

it('creates session with seed state', function () {
    $factory = new SessionFactory(new DefinitionStateFactory());
    $definition = new AgentDefinition(
        name: 'agent',
        description: 'test',
        systemPrompt: 'Prompt',
    );
    $seed = AgentState::empty()->withSystemPrompt('Seed prompt');

    $session = $factory->create($definition, $seed);

    // DefinitionStateFactory applies definition's systemPrompt over seed
    expect($session->state()->context()->systemPrompt())->toBe('Prompt');
});

it('uses definition label fallback to name', function () {
    $factory = new SessionFactory(new DefinitionStateFactory());
    $definition = new AgentDefinition(
        name: 'my-agent',
        description: 'test',
        systemPrompt: 'Prompt',
    );

    $session = $factory->create($definition);

    expect($session->info()->agentLabel())->toBe('my-agent');
});
