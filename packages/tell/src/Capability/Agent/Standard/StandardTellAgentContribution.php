<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Agent\Standard;

use Cognesy\Agents\Capability\Definitions\UseAgentDefinitions;
use Cognesy\Agents\Capability\Describe\UseSelfDescription;
use Cognesy\Agents\Capability\Prompt\UseSystemPrompt;
use Cognesy\Agents\Capability\SelfKnowledge\UseSelfKnowledge;
use Cognesy\Agents\Template\AgentDefinitionValidator;
use Cognesy\Tell\Core\Agent\TellAgentAssembly;
use Cognesy\Tell\Core\Contract\Agent\CanContributeTellAgent;

final readonly class StandardTellAgentContribution implements CanContributeTellAgent
{
    #[\Override]
    public function contribute(TellAgentAssembly $assembly): void {
        $assembly->capabilities->register('tell.system_prompt', new UseSystemPrompt());
        $assembly->capabilities->register('tell.self_knowledge', new UseSelfKnowledge());
        $assembly->capabilities->register('tell.self_description', new UseSelfDescription());
        $assembly->capabilities->register('tell.agent_definitions', new UseAgentDefinitions(
            registry: $assembly->definitions,
            validator: new AgentDefinitionValidator($assembly->capabilities, $assembly->tools),
        ));
    }
}
