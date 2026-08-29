<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Coding;

use Cognesy\Agents\Tool\ToolDescriptor;
use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
use Override;

/** Backward-compatible Tell name for the apply_patch operation. */
final class EditAliasTool extends SimpleTool
{
    public function __construct(private readonly PatchOperation $operation) {
        parent::__construct(new ToolDescriptor(
            name: 'edit',
            description: 'Compatibility alias for apply_patch using the previous exact replacement schema.',
            metadata: [
                'name' => 'edit',
                'canonicalName' => 'apply_patch',
                'aliasOf' => 'apply_patch',
                'effect' => 'write',
                'bounds' => ['sourceBytesPerFile' => 2_097_152, 'existingFilesOnly' => true],
                'tags' => ['file', 'patch', 'compatibility'],
            ],
            instructions: [
                'parameters' => [
                    'path' => 'Existing file path under the Tell working directory.',
                    'old_string' => 'Exact text to replace.',
                    'new_string' => 'Replacement text.',
                    'replace_all' => 'Replace every occurrence; otherwise the match must be unique.',
                ],
                'canonicalName' => 'apply_patch',
                'aliasOf' => 'apply_patch',
                'returns' => 'Structured result: success, data, error, truncated, and partial.',
            ],
        ));
    }

    #[Override]
    public function __invoke(mixed ...$args): array {
        return $this->operation->replace(
            path: (string) $this->arg($args, 'path', 0, ''),
            old: (string) $this->arg($args, 'old_string', 1, ''),
            new: (string) $this->arg($args, 'new_string', 2, ''),
            replaceAll: (bool) $this->arg($args, 'replace_all', 3, false),
        );
    }

    #[Override]
    public function toToolSchema(): ToolDefinition {
        return ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('path', 'Existing file to edit.'),
                    JsonSchema::string('old_string', 'Exact text to find.'),
                    JsonSchema::string('new_string', 'Replacement text.'),
                    JsonSchema::boolean('replace_all', 'Replace all occurrences when true.'),
                ])
                ->withRequiredProperties(['path', 'old_string', 'new_string']),
        )->toArray());
    }
}
