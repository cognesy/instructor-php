<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Session;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\Contracts\CanControlAgentSession;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Data\AgentSessionInfo;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Session\Enums\AgentSessionStage;
use Cognesy\Agents\Session\SessionHookStack;
use Cognesy\Agents\Template\Data\AgentDefinition;

function makeHookStackSession(string $id): AgentSession {
    return new AgentSession(
        header: AgentSessionInfo::fresh(SessionId::from($id), 'agent', 'Agent'),
        definition: new AgentDefinition(name: 'agent', description: 'test', systemPrompt: 'Test'),
        state: AgentState::empty(),
    );
}

it('runs higher priority hooks first', function () {
    $session = makeHookStackSession('stack-priority');

    $high = new class implements CanControlAgentSession {
        public function onStage(AgentSessionStage $stage, AgentSession $session): AgentSession {
            if ($stage !== AgentSessionStage::AfterAction) {
                return $session;
            }

            $order = $session->state()->metadata()->get('order');
            $values = is_array($order) ? $order : [];
            $values[] = 'high';
            return $session->withState($session->state()->withMetadata('order', $values));
        }
    };

    $low = new class implements CanControlAgentSession {
        public function onStage(AgentSessionStage $stage, AgentSession $session): AgentSession {
            if ($stage !== AgentSessionStage::AfterAction) {
                return $session;
            }

            $order = $session->state()->metadata()->get('order');
            $values = is_array($order) ? $order : [];
            $values[] = 'low';
            return $session->withState($session->state()->withMetadata('order', $values));
        }
    };

    $stack = SessionHookStack::empty()
        ->with($low, priority: 10)
        ->with($high, priority: 200);

    $next = $stack->onStage(AgentSessionStage::AfterAction, $session);

    expect($next->state()->metadata()->get('order'))->toBe(['high', 'low']);
});

