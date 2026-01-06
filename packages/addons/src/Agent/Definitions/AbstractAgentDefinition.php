<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Definitions;

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\Contracts\AgentContract;
use Cognesy\Addons\Agent\Core\Data\AgentDescriptor;
use Cognesy\Addons\Agent\Core\Data\AgentState;

abstract class AbstractAgentDefinition implements AgentContract
{
    private ?Agent $agent = null;

    abstract public function descriptor(): AgentDescriptor;

    abstract protected function buildAgent(): Agent;

    #[\Override]
    public function build(): Agent {
        if ($this->agent !== null) {
            return $this->agent;
        }
        $this->agent = $this->buildAgent();
        return $this->agent;
    }

    #[\Override]
    public function run(AgentState $state): AgentState {
        return $this->finalStep($state);
    }

    #[\Override]
    public function nextStep(object $state): object {
        return $this->build()->nextStep($state);
    }

    #[\Override]
    public function hasNextStep(object $state): bool {
        return $this->build()->hasNextStep($state);
    }

    #[\Override]
    public function finalStep(object $state): object {
        return $this->build()->finalStep($state);
    }

    #[\Override]
    public function iterator(object $state): iterable {
        return $this->build()->iterator($state);
    }
}
