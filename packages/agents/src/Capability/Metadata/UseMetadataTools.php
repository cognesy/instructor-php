<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Metadata;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Hook\Collections\HookTriggers;

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
final class UseMetadataTools implements CanProvideAgentCapability
{
    public function __construct(
        private ?MetadataPolicy $policy = null,
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_metadata_tools';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $policy = $this->policy ?? new MetadataPolicy();

        $agent = $agent->withTools($agent->tools()->merge(new Tools(
            new MetadataWriteTool($policy),
            new MetadataReadTool(),
            new MetadataListTool(),
        )));

        $hooks = $agent->hooks()->with(
            hook: new PersistMetadataHook(),
            triggerTypes: HookTriggers::afterStep(),
        );
        return $agent->withHooks($hooks);
    }
}
