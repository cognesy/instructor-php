<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item;

/**
 * MCP tool invocation
 *
 * Example: {"id":"item_7","type":"mcp_tool_call","server":"database","tool":"query","status":"in_progress"}
 */
final readonly class McpToolCall extends Item
{
    /**
     * @param array<string, mixed>|null $arguments
     * @param array<string, mixed>|null $result
     */
    public function __construct(
        string $id,
        string $status,
        public string $server,
        public string $tool,
        public ?array $arguments = null,
        public ?array $result = null,
        public ?string $error = null,
    ) {
        parent::__construct($id, $status);
    }

    public function itemType(): string
    {
        return 'mcp_tool_call';
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)($data['id'] ?? ''),
            status: (string)($data['status'] ?? 'in_progress'),
            server: (string)($data['server'] ?? ''),
            tool: (string)($data['tool'] ?? ''),
            arguments: isset($data['arguments']) && is_array($data['arguments']) ? $data['arguments'] : null,
            result: isset($data['result']) && is_array($data['result']) ? $data['result'] : null,
            error: isset($data['error']) ? (string)$data['error'] : null,
        );
    }
}
