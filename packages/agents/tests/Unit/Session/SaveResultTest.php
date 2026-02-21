<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Session;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\AgentSessionInfo;
use Cognesy\Agents\Session\SaveResult;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Template\Data\AgentDefinition;

it('ok creates success result with session', function () {
    $session = new AgentSession(
        header: AgentSessionInfo::fresh(new SessionId('s1'), 'agent', 'Agent'),
        definition: new AgentDefinition(name: 'agent', description: 'test', systemPrompt: 'Test'),
        state: AgentState::empty(),
    );
    $result = SaveResult::ok($session);

    expect($result->isOk())->toBeTrue();
    expect($result->isConflict())->toBeFalse();
    expect($result->session)->toBe($session);
    expect($result->message)->toBeNull();
});

it('conflict creates failure result', function () {
    $result = SaveResult::conflict('Version mismatch');

    expect($result->isOk())->toBeFalse();
    expect($result->isConflict())->toBeTrue();
    expect($result->message)->toBe('Version mismatch');
    expect($result->session)->toBeNull();
});
