<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\StructuredOutput;

/**
 * Defines a schema with optional per-schema configuration.
 *
 * Use this when you need custom extraction behavior for a specific schema,
 * such as a custom prompt, examples, or retry settings.
 *
 * Example:
 *   new SchemaDefinition(
 *       class: LeadForm::class,
 *       description: 'Business lead with contact info',
 *       prompt: 'Extract lead information. Prioritize decision-maker names.',
 *       examples: [
 *           ['input' => 'CEO John at Acme', 'output' => ['name' => 'John', 'role' => 'CEO']]
 *       ],
 *       maxRetries: 5,
 *   )
 */
final readonly class SchemaDefinition
{
    /**
     * @param class-string $class The PHP class to extract into
     * @param string|null $description Human-readable description for the agent
     * @param string|null $prompt Custom extraction prompt
     * @param array|null $examples Few-shot examples for better extraction
     * @param int|null $maxRetries Override default retry count
     * @param array|null $llmOptions LLM-specific options (temperature, etc.)
     */
    public function __construct(
        public string $class,
        public ?string $description = null,
        public ?string $prompt = null,
        public ?array $examples = null,
        public ?int $maxRetries = null,
        public ?array $llmOptions = null,
    ) {}

    /**
     * Create from just a class name (simplest case).
     * @param class-string $class
     */
    public static function fromClass(string $class): self {
        return new self(class: $class);
    }

    /**
     * Create with a description (for schema listing).
     * @param class-string $class
     */
    public static function withDescription(string $class, string $description): self {
        return new self(class: $class, description: $description);
    }
}
