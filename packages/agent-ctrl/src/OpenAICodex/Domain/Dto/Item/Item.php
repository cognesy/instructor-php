<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item;

/**
 * Base class for all item types in Codex CLI streaming
 *
 * Item types:
 * - agent_message - Text message from the agent
 * - command_execution - Shell command execution
 * - file_change - File modification
 * - mcp_tool_call - MCP tool invocation
 * - web_search - Web search result
 * - plan_update - Plan modification
 * - reasoning - Internal reasoning step
 */
abstract readonly class Item
{
    public function __construct(
        public string $id,
        public string $status,
    ) {}

    abstract public function itemType(): string;

    /**
     * Create appropriate Item subclass from raw data
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $type = $data['type'] ?? 'unknown';

        return match ($type) {
            'agent_message' => AgentMessage::fromArray($data),
            'command_execution' => CommandExecution::fromArray($data),
            'file_change' => FileChange::fromArray($data),
            'mcp_tool_call' => McpToolCall::fromArray($data),
            'web_search' => WebSearch::fromArray($data),
            'plan_update' => PlanUpdate::fromArray($data),
            'reasoning' => Reasoning::fromArray($data),
            default => UnknownItem::fromArray($data),
        };
    }
}
