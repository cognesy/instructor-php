<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\StructuredOutput;

use Cognesy\Agents\Capability\StructuredOutput\CanManageSchemas;
use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Extras\Maybe\Maybe;
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
        private CanCreateStructuredOutput $structuredOutput,
        private StructuredOutputPolicy $policy = new StructuredOutputPolicy(),
    ) {
        parent::__construct(new StructuredOutputToolDescriptor($schemas));
    }

    #[\Override]
    public function __invoke(mixed ...$args): StructuredOutputResult {
        $input = $this->arg($args, 'input', 0, '');
        $schemaName = $this->arg($args, 'schema', 1, '');
        $storeAs = $this->arg($args, 'store_as', 2);

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
            $data = $this->extract($input, $schemaName);

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

    private function extract(string $input, string $schemaName): mixed {
        $schema = $this->schemas->get($schemaName);
        $responseModel = $this->policy->useMaybe ? Maybe::is($schema->class) : $schema->class;
        $request = $this->policy->withRequest(new StructuredOutputRequest(
            messages: $input,
            requestedSchema: $responseModel,
        ));
        $request = $schema->withRequest($request);
        $result = $this->structuredOutput->create($request)->get();
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
                ])
                ->withRequiredProperties(['input', 'schema'])
        )->toArray();
    }
}
