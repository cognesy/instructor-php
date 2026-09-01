<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Tool\Coding;

use Cognesy\Agents\Tool\Contracts\ToolInterface;
use Cognesy\Agents\Tool\ToolDescriptor;
use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Override;

/**
 * Gives a Tell vocabulary name and stable result envelope to one existing tool.
 * The wrapped tool remains the sole implementation and policy authority.
 */
final class StructuredToolAdapter extends SimpleTool
{
    /** @param array<string, scalar|array<array-key, scalar>> $bounds */
    public function __construct(
        private readonly ToolInterface $operation,
        string $name,
        private readonly string $effect,
        private readonly array $bounds = [],
    ) {
        $source = $operation->descriptor();
        parent::__construct(new ToolDescriptor(
            name: $name,
            description: $source->description(),
            metadata: [
                ...$source->metadata(),
                'name' => $name,
                'effect' => $effect,
                'bounds' => $bounds,
            ],
            instructions: [
                ...$source->instructions(),
                'name' => $name,
                'effect' => $effect,
                'bounds' => $bounds,
                'returns' => 'Structured result: success, data, error, truncated, and partial.',
            ],
        ));
    }

    #[Override]
    public function __invoke(mixed ...$args): array {
        $result = $this->operation->use(...$args);
        if ($result->isFailure()) {
            return $this->failure('operation_exception', $result->exception()->getMessage());
        }

        $value = $result->unwrap();
        $text = is_string($value) ? $value : (string) json_encode($value, JSON_THROW_ON_ERROR);
        if (str_contains($text, 'Command timed out')) {
            return $this->failure('timeout', 'Tool execution exceeded its time limit.', $text);
        }
        if (str_starts_with($text, 'Error:')) {
            return $this->failure('operation_failed', trim(substr($text, strlen('Error:'))), $text);
        }

        return [
            'success' => true,
            'data' => ['text' => $text],
            'error' => null,
            'truncated' => str_contains(strtolower($text), 'truncated'),
            'partial' => false,
        ];
    }

    #[Override]
    public function toToolSchema(): ToolDefinition {
        return new ToolDefinition(
            name: $this->name(),
            description: $this->description(),
            parameters: $this->operation->toToolSchema()->parameters(),
        );
    }

    /** @return array{success: false, data: array<string, string>, error: array{code: string, message: string}, truncated: false, partial: false} */
    private function failure(string $code, string $message, ?string $text = null): array {
        return [
            'success' => false,
            'data' => $text === null ? [] : ['text' => $text],
            'error' => ['code' => $code, 'message' => $message],
            'truncated' => false,
            'partial' => false,
        ];
    }
}
