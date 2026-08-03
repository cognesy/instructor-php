<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Definitions;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Template\AgentDefinitionSerializer;
use Cognesy\Agents\Template\AgentDefinitionValidator;
use Cognesy\Agents\Template\Contracts\CanManageAgentDefinitions;
use Cognesy\Agents\Template\FileAgentDefinitionStore;
use Override;

final readonly class UseAgentDefinitions implements CanProvideAgentCapability
{
    public function __construct(
        private CanManageAgentDefinitions $registry,
        private AgentDefinitionValidator $validator,
        private ?FileAgentDefinitionStore $store = null,
    ) {}

    #[Override]
    public static function capabilityName(): string {
        return 'use_agent_definitions';
    }

    #[Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $serializer = new AgentDefinitionSerializer();
        $tools = [
            new ListAgentsTool($this->registry),
            new ReadAgentTool($this->registry, $serializer),
        ];
        if ($this->store !== null) {
            $tools[] = new WriteAgentTool($this->registry, $this->validator, $this->store);
        }
        return $agent->withTools($agent->tools()->merge(new Tools(...$tools)));
    }
}
