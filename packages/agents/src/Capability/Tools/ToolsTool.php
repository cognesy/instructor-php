<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Tools;

use Cognesy\Agents\Tool\Contracts\CanManageTools;
use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

final class ToolsTool extends SimpleTool
{
    public function __construct(
        private readonly CanManageTools $registry,
    ) {
        parent::__construct(new ToolsToolDescriptor());
    }

    #[\Override]
    public function __invoke(mixed ...$args): array
    {
        $action = $this->arg($args, 'action', 0, 'list');

        return match ($action) {
            'list' => $this->handleList($args),
            'help' => $this->handleHelp($args),
            'search' => $this->handleSearch($args),
            default => [
                'success' => false,
                'error' => "Unknown action '{$action}'. Use list, help, or search.",
            ],
        };
    }

    #[\Override]
    public function toToolSchema(): array
    {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::enum('action', ['list', 'help', 'search'], 'Action to perform: list tools, get help on a tool, or search tools.'),
                    JsonSchema::string('tool', 'Tool name to get full spec for (when action=help).'),
                    JsonSchema::string('query', 'Search query (when action=search).'),
                    JsonSchema::integer('limit', 'Maximum number of results to return.', meta: ['minimum' => 1, 'maximum' => 100]),
                ])
        )->toArray();
    }

    private function handleList(array $args): array
    {
        $limit = $this->resolveLimit($args);
        $tools = [];
        foreach ($this->registry->names() as $name) {
            $tools[] = $this->registry->get($name)->descriptor()->metadata();
        }

        return [
            'success' => true,
            'count' => count($tools),
            'tools' => $limit !== null ? array_slice($tools, 0, $limit) : $tools,
        ];
    }

    private function handleHelp(array $args): array
    {
        $toolName = $args['tool'] ?? $args['name'] ?? null;
        if (!is_string($toolName) || $toolName === '') {
            return [
                'success' => false,
                'error' => 'Tool name is required for help.',
            ];
        }

        if (!$this->registry->has($toolName)) {
            return [
                'success' => false,
                'error' => "Tool '{$toolName}' not found.",
            ];
        }

        return [
            'success' => true,
            'tool' => $this->registry->get($toolName)->descriptor()->instructions(),
        ];
    }

    private function handleSearch(array $args): array
    {
        $query = $args['query'] ?? null;
        if (!is_string($query) || trim($query) === '') {
            return [
                'success' => false,
                'error' => 'Search query is required.',
            ];
        }

        $limit = $this->resolveLimit($args);
        $needle = mb_strtolower(trim($query));
        $results = [];
        foreach ($this->registry->names() as $name) {
            $tool = $this->registry->get($name);
            $descriptor = $tool->descriptor();
            $metadata = $descriptor->metadata();
            $fullSpec = $descriptor->instructions();
            $description = $metadata['description'] ?? $fullSpec['description'] ?? '';
            $summary = $metadata['summary'] ?? '';
            $namespace = $metadata['namespace'] ?? '';
            $tags = $metadata['tags'] ?? [];

            $haystack = implode(' ', array_filter([
                $metadata['name'] ?? $name,
                $summary,
                $description,
                $namespace,
                is_array($tags) ? implode(' ', $tags) : (string) $tags,
            ]));

            if (mb_strtolower($haystack) === '') {
                continue;
            }

            if (mb_strpos(mb_strtolower($haystack), $needle) === false) {
                continue;
            }

            $metadata['description'] = $description;
            $results[] = $metadata;
        }

        return [
            'success' => true,
            'query' => $query,
            'count' => count($results),
            'tools' => $limit !== null ? array_slice($results, 0, $limit) : $results,
        ];
    }

    private function resolveLimit(array $args): ?int
    {
        $limit = $args['limit'] ?? null;
        if (!is_int($limit)) {
            return null;
        }

        return max(1, min(100, $limit));
    }
}
