<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Metadata;

use Cognesy\Agents\Agent\Collections\Tools;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Contracts\AgentCapability;

/**
 * Adds metadata read/write/list tools to the agent.
 *
 * This capability gives the agent a "scratchpad" to store and retrieve
 * data between tool calls. Useful for multi-step workflows where one tool
 * produces data that another tool needs to consume.
 *
 * Example workflow:
 *   1. extract_data(input: "...", schema: "lead", store_as: "current_lead")
 *   2. save_lead(metadata_key: "current_lead")
 */
class UseMetadataTools implements AgentCapability
{
    public function __construct(
        private ?MetadataPolicy $policy = null,
    ) {}

    #[\Override]
    public function install(AgentBuilder $builder): void {
        $policy = $this->policy ?? new MetadataPolicy();

        $builder->withTools(new Tools(
            new MetadataWriteTool($policy),
            new MetadataReadTool(),
            new MetadataListTool(),
        ));

        $builder->addProcessor(new PersistMetadataProcessor());
    }
}
