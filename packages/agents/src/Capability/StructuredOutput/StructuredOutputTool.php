<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\StructuredOutput;

use Cognesy\Agents\Capability\StructuredOutput\CanManageSchemas;
use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Instructor\Extras\Maybe\Maybe;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
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
final class StructuredOutputTool extends SimpleTool
{
    public const TOOL_NAME = 'structured_output';

    public function __construct(
        private CanManageSchemas $schemas,
        private StructuredOutputPolicy $policy = new StructuredOutputPolicy(),
    ) {
        parent::__construct(new StructuredOutputToolDescriptor($schemas));
    }

    #[\Override]
    public function __invoke(mixed ...$args): StructuredOutputResult {
        $input = $this->arg($args, 'input', 0, '');
        $schemaName = $this->arg($args, 'schema', 1, '');
        $storeAs = $this->arg($args, 'store_as', 2);
        $maxRetries = $this->arg($args, 'max_retries', 3);

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

        $this->policy->applyTo($instructor);
        $schema->applyTo($instructor);

        $retries = $maxRetries ?? $schema->maxRetries ?? $this->policy->defaultMaxRetries;
        $responseModel = $this->policy->useMaybe ? Maybe::is($schema->class) : $schema->class;

        $result = $instructor
            ->withMaxRetries($retries)
            ->withMessages($input)
            ->withResponseModel($responseModel)
            ->get();

        return $this->unwrapResult($result, $schemaName);
    }

    private function unwrapResult(mixed $result, string $schemaName): mixed {
        if (!$this->policy->useMaybe || !$result instanceof Maybe) {
            return $result;
        }

        if (!$result->hasValue()) {
            throw new \RuntimeException(
                "Could not extract {$schemaName}: " . ($result->error() ?: 'Unknown error')
            );
        }

        return $result->get();
    }

    #[\Override]
    public function toToolSchema(): array {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('input', 'The unstructured text to extract data from'),
                    JsonSchema::enum('schema', $this->schemas->names(), 'Name of the schema to extract into'),
                    JsonSchema::string('store_as', 'Optional: metadata key to store the extracted data for use by other tools'),
                    JsonSchema::integer('max_retries', 'Optional: maximum retry attempts on validation failure (default: 3)'),
                ])
                ->withRequiredProperties(['input', 'schema'])
        )->toArray();
    }
}
