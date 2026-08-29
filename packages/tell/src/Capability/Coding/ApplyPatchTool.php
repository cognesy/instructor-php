<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Coding;

use Cognesy\Agents\Tool\ToolDescriptor;
use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
use Override;

final class ApplyPatchTool extends SimpleTool
{
    public function __construct(private readonly PatchOperation $operation) {
        parent::__construct(new ToolDescriptor(
            name: 'apply_patch',
            description: 'Apply a bounded unified diff to existing text files under the Tell working directory.',
            metadata: [
                'name' => 'apply_patch',
                'canonicalName' => 'apply_patch',
                'aliasOf' => null,
                'effect' => 'write',
                'bounds' => ['patchBytes' => 262_144, 'sourceBytesPerFile' => 2_097_152, 'existingFilesOnly' => true],
                'tags' => ['file', 'patch', 'atomic'],
            ],
            instructions: [
                'parameters' => ['patch' => 'Unified diff using ---/+++ headers and @@ hunks; only existing regular files are supported.'],
                'returns' => 'Structured result: success, data, error, truncated, and partial. All hunks are validated before any file is changed.',
                'errors' => ['Traversal and symlink paths are denied.', 'Malformed or non-matching hunks do not change files.'],
            ],
        ));
    }

    #[Override]
    public function __invoke(mixed ...$args): array {
        return $this->operation->applyUnified((string) $this->arg($args, 'patch', 0, ''));
    }

    #[Override]
    public function toToolSchema(): ToolDefinition {
        return ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([JsonSchema::string('patch', 'Bounded unified diff to apply.')])
                ->withRequiredProperties(['patch']),
        )->toArray());
    }
}
