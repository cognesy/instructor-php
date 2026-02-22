<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Actions;

use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\Contracts\CanExecuteSessionAction;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

final readonly class ChangeModel implements CanExecuteSessionAction
{
    public function __construct(
        private ?LLMConfig $llmConfig,
    ) {}

    #[\Override]
    public function executeOn(AgentSession $session): AgentSession {
        return $session->withState($session->state()->withLLMConfig($this->llmConfig));
    }
}
