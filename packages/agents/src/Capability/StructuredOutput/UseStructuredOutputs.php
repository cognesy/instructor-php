<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\StructuredOutput;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Capability\StructuredOutput\CanManageSchemas;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;

/**
 * Adds structured output extraction capability to the agent.
 *
 * This capability enables the agent to extract structured data from
 * unstructured text using Instructor's LLM-powered extraction.
 *
 * Configuration layers (in order of precedence):
 * 1. Tool call parameters (max_retries, store_as)
 * 2. Per-schema config (SchemaDefinition)
 * 3. Capability-level defaults (this class)
 *
 * Example:
 *   $schemas = new SchemaRegistry();
 *   $schemas->register('lead', LeadForm::class);
 *   $schemas->register('contact', new SchemaDefinition(
 *       class: ContactForm::class,
 *       prompt: 'Extract contact information, prioritizing email and phone',
 *       maxRetries: 5,
 *   ));
 *
 *   $agent = AgentBuilder::base()
 *       ->withCapability(new UseStructuredOutputs(
 *           schemas: $schemas,
 *           policy: new StructuredOutputPolicy(llmPreset: 'anthropic'),
 *       ))
 *       ->build();
 */
class UseStructuredOutputs implements CanProvideAgentCapability
{
    public function __construct(
        private CanManageSchemas $schemas,
        private CanCreateStructuredOutput $structuredOutput,
        private ?StructuredOutputPolicy $policy = null,
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_structured_outputs';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $policy = $this->policy ?? new StructuredOutputPolicy();

        $agent = $agent->withTools($agent->tools()->merge(new Tools(
            new StructuredOutputTool($this->schemas, $this->structuredOutput, $policy),
        )));

        // Handles store_as functionality
        $hooks = $agent->hooks()->with(
            hook: new PersistStructuredOutputHook(),
            triggerTypes: HookTriggers::afterStep(),
        );
        return $agent->withHooks($hooks);
    }
}
