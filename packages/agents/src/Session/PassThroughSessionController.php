<?php declare(strict_types=1);

namespace Cognesy\Agents\Session;

use Cognesy\Agents\Session\Contracts\CanControlAgentSession;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Enums\AgentSessionStage;

final class PassThroughSessionController implements CanControlAgentSession
{
    public static function default(): self {
        return new self();
    }

    #[\Override]
    public function onStage(AgentSessionStage $stage, AgentSession $session): AgentSession {
        return $session;
    }
}
