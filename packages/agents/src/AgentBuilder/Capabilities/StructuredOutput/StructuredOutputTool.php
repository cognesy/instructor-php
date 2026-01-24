<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\StructuredOutput;

use Cognesy\Agents\Agent\Tools\BaseTool;
use Cognesy\Instructor\Extras\Maybe\Maybe;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Utils\Json\Json;
use Throwable;

/**
 * Tool for extracting structured data from unstructured text.
 *
 * Uses Instructor to transform text into validated PHP objects
 * based on pre-registered schemas.
 *
 * Example agent usage:
 *   structured_output(
 *       input: "John Smith, CEO of Acme Corp. Email: john@acme.com",
 *       schema: "lead",
 *       store_as: "current_lead"
 *   )
 */
class StructuredOutputTool extends BaseTool
{
    public const TOOL_NAME = 'structured_output';

    public function __construct(
        private SchemaRegistry $schemas,
        private StructuredOutputPolicy $policy = new StructuredOutputPolicy(),
    ) {
        parent::__construct(
            name: self::TOOL_NAME,
            description: $this->buildDescription(),
        );
    }

    #[\Override]
    public function __invoke(mixed ...$args): StructuredOutputResult {
        $input = $args['input'] ?? $args[0] ?? '';
        $schemaName = $args['schema'] ?? $args[1] ?? '';
        $storeAs = $args['store_as'] ?? $args[2] ?? null;
        $maxRetries = $args['max_retries'] ?? $args[3] ?? null;

        // Validate input
        if ($input === '') {
            return StructuredOutputResult::failure($schemaName, 'Input cannot be empty');
        }

        if ($schemaName === '') {
            return StructuredOutputResult::failure($schemaName, 'Schema name is required');
        }

        if (!$this->schemas->has($schemaName)) {
            $available = implode(', ', $this->schemas->names());
            return StructuredOutputResult::failure(
                $schemaName,
                "Schema '{$schemaName}' not found. Available: {$available}"
            );
        }

        try {
            $data = $this->extract($input, $schemaName, $maxRetries);

            return StructuredOutputResult::success(
                schema: $schemaName,
                data: $data,
                storeAs: $storeAs,
            );
        } catch (Throwable $e) {
            return StructuredOutputResult::failure(
                $schemaName,
                $e->getMessage()
            );
        }
    }

    private function extract(string $input, string $schemaName, ?int $maxRetries): mixed {
        $schema = $this->schemas->get($schemaName);

        $instructor = new StructuredOutput();

        // Apply policy-level config
        if ($this->policy->llmPreset !== null) {
            $instructor->using($this->policy->llmPreset);
        }

        if ($this->policy->model !== null) {
            $instructor->withModel($this->policy->model);
        }

        if ($this->policy->outputMode !== null) {
            $instructor->withOutputMode($this->policy->outputMode);
        }

        if ($this->policy->systemPrompt !== null) {
            $instructor->withSystem($this->policy->systemPrompt);
        }

        // Apply schema-level config
        if ($schema->prompt !== null) {
            $instructor->withPrompt($schema->prompt);
        }

        if ($schema->examples !== null) {
            $instructor->withExamples($schema->examples);
        }

        if ($schema->llmOptions !== null) {
            $instructor->withOptions($schema->llmOptions);
        }

        // Determine max retries (tool param > schema > policy)
        $retries = $maxRetries
            ?? $schema->maxRetries
            ?? $this->policy->defaultMaxRetries;

        $instructor->withMaxRetries($retries);

        // Determine response model
        $responseModel = $this->policy->useMaybe
            ? Maybe::is($schema->class)
            : $schema->class;

        // Execute extraction
        $result = $instructor
            ->withMessages($input)
            ->withResponseModel($responseModel)
            ->get();

        // Handle Maybe wrapper
        if ($this->policy->useMaybe && $result instanceof Maybe) {
            if (!$result->hasValue()) {
                throw new \RuntimeException(
                    "Could not extract {$schemaName}: " . ($result->error() ?: 'Unknown error')
                );
            }
            return $result->get();
        }

        return $result;
    }

    private function buildDescription(): string {
        $schemaList = $this->schemas->list();
        $schemaDescriptions = [];

        foreach ($schemaList as $name => $description) {
            $schemaDescriptions[] = $description !== null
                ? "- {$name}: {$description}"
                : "- {$name}";
        }

        $schemasText = $schemaDescriptions !== []
            ? "\n\nAvailable schemas:\n" . implode("\n", $schemaDescriptions)
            : '';

        return <<<DESC
Extract structured data from unstructured text using a predefined schema.

The extraction uses AI to parse the input and populate a validated data structure.
If extraction fails validation, the system will retry automatically.

Use 'store_as' parameter to save the extracted data in agent metadata for use by other tools.
{$schemasText}
DESC;
    }

    #[\Override]
    public function toToolSchema(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'input' => [
                            'type' => 'string',
                            'description' => 'The unstructured text to extract data from',
                        ],
                        'schema' => [
                            'type' => 'string',
                            'description' => 'Name of the schema to extract into',
                            'enum' => $this->schemas->names(),
                        ],
                        'store_as' => [
                            'type' => 'string',
                            'description' => 'Optional: metadata key to store the extracted data for use by other tools',
                        ],
                        'max_retries' => [
                            'type' => 'integer',
                            'description' => 'Optional: maximum retry attempts on validation failure (default: 3)',
                        ],
                    ],
                    'required' => ['input', 'schema'],
                ],
            ],
        ];
    }
}
