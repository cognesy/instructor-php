<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Tools;

use Cognesy\Addons\Agent\Contracts\ToolRegistryContract;

final class ToolsTool extends BaseTool
{
    public function __construct(
        private readonly ToolRegistryContract $registry,
        private readonly ?string $locale = null,
    ) {
        parent::__construct(
            name: 'tools',
            description: 'List, search, and inspect available tools. Use to discover tools without loading full specs.',
        );
    }

    #[\Override]
    public function __invoke(mixed ...$args): array
    {
        $action = $args['action'] ?? null;
        $command = $args['command'] ?? null;

        if ($action === null && is_string($command)) {
            [$action, $args] = $this->parseCommand($command, $args);
        }

        $action = $action ?? 'list';

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
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'action' => [
                            'type' => 'string',
                            'enum' => ['list', 'help', 'search'],
                            'description' => 'Action to perform: list tools, get help on a tool, or search tools.',
                        ],
                        'tool' => [
                            'type' => 'string',
                            'description' => 'Tool name to get full spec for (when action=help).',
                        ],
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search query (when action=search).',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of results to return.',
                            'minimum' => 1,
                            'maximum' => 100,
                        ],
                        'command' => [
                            'type' => 'string',
                            'description' => 'CLI-like fallback: "--list", "--help <tool>", or "--search <text>".',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $args
     */
    private function handleList(array $args): array
    {
        $limit = $this->resolveLimit($args);
        $tools = $this->registry->listMetadata($this->locale);

        return [
            'success' => true,
            'count' => count($tools),
            'tools' => $limit !== null ? array_slice($tools, 0, $limit) : $tools,
        ];
    }

    /**
     * @param array<string, mixed> $args
     */
    private function handleHelp(array $args): array
    {
        $toolName = $args['tool'] ?? $args['name'] ?? null;
        if (!is_string($toolName) || $toolName === '') {
            return [
                'success' => false,
                'error' => 'Tool name is required for help.',
            ];
        }

        if (! $this->registry->has($toolName)) {
            return [
                'success' => false,
                'error' => "Tool '{$toolName}' not found.",
            ];
        }

        $specs = $this->registry->listFullSpecs($this->locale);
        foreach ($specs as $spec) {
            if (($spec['name'] ?? null) === $toolName) {
                return [
                    'success' => true,
                    'tool' => $spec,
                ];
            }
        }

        return [
            'success' => false,
            'error' => "Tool '{$toolName}' spec not found.",
        ];
    }

    /**
     * @param array<string, mixed> $args
     */
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
        $results = $this->registry->search($query, $this->locale);

        return [
            'success' => true,
            'query' => $query,
            'count' => count($results),
            'tools' => $limit !== null ? array_slice($results, 0, $limit) : $results,
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @return array{0: string|null, 1: array<string, mixed>}
     */
    private function parseCommand(string $command, array $args): array
    {
        $command = trim($command);
        if ($command === '') {
            return [null, $args];
        }

        if (str_contains($command, '--list')) {
            return ['list', $args];
        }

        if (preg_match('/--help\\s+(\\S+)/', $command, $matches)) {
            $args['tool'] = $matches[1];
            return ['help', $args];
        }

        if (preg_match('/--search\\s+(.+)$/', $command, $matches)) {
            $args['query'] = trim($matches[1], "\"' ");
            return ['search', $args];
        }

        return [null, $args];
    }

    /**
     * @param array<string, mixed> $args
     */
    private function resolveLimit(array $args): ?int
    {
        $limit = $args['limit'] ?? null;
        if (!is_int($limit)) {
            return null;
        }

        return max(1, min(100, $limit));
    }
}
