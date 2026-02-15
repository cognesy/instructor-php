<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\StructuredOutput;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class StructuredOutputToolDescriptor extends ToolDescriptor
{
    public function __construct(CanManageSchemas $schemas) {
        parent::__construct(
            name: StructuredOutputTool::TOOL_NAME,
            description: $this->buildDescription($schemas),
            metadata: [
                'name' => StructuredOutputTool::TOOL_NAME,
                'summary' => 'Extract structured data from text using predefined schemas.',
                'namespace' => 'structured_output',
                'tags' => ['extract', 'schema', 'validation'],
            ],
            instructions: [
                'parameters' => [
                    'input' => 'Unstructured text to extract from.',
                    'schema' => 'Registered schema name.',
                    'store_as' => 'Optional metadata key to persist extracted data.',
                    'max_retries' => 'Optional extraction retry limit.',
                ],
                'returns' => 'StructuredOutputResult with data or failure details.',
            ],
        );
    }

    private function buildDescription(CanManageSchemas $schemas): string {
        $schemaDescriptions = [];
        foreach ($schemas->names() as $name) {
            $description = $schemas->get($name)->description;
            $schemaDescriptions[] = match (true) {
                $description !== null => "- {$name}: {$description}",
                default => "- {$name}",
            };
        }

        $schemasText = match (true) {
            $schemaDescriptions !== [] => "\n\nAvailable schemas:\n" . implode("\n", $schemaDescriptions),
            default => '',
        };

        return <<<DESC
Extract structured data from unstructured text using a predefined schema.

The extraction uses AI to parse the input and populate a validated data structure.
If extraction fails validation, the system will retry automatically.

Use 'store_as' parameter to save the extracted data in agent metadata for use by other tools.
{$schemasText}
DESC;
    }
}
