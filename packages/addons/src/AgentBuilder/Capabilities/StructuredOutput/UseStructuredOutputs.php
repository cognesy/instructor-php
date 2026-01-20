<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentBuilder\Capabilities\StructuredOutput;

use Cognesy\Addons\AgentBuilder\AgentBuilder;
use Cognesy\Addons\AgentBuilder\Contracts\AgentCapability;
use Cognesy\Addons\Agent\Core\Collections\Tools;

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
 *           llmPreset: 'anthropic',
 *       ))
 *       ->build();
 */
class UseStructuredOutputs implements AgentCapability
{
    public function __construct(
        private SchemaRegistry $schemas,
        private ?StructuredOutputPolicy $policy = null,
    ) {}

    #[\Override]
    public function install(AgentBuilder $builder): void {
        $policy = $this->policy ?? new StructuredOutputPolicy();

        $builder->withTools(new Tools(
            new StructuredOutputTool($this->schemas, $policy),
        ));

        // Reuse metadata processor if not already added
        // (handles store_as functionality)
        $builder->addProcessor(new PersistStructuredOutputProcessor());
    }
}
