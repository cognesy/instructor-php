# PRD: MCP (Model Context Protocol) Integration

**Priority**: P3
**Impact**: Medium
**Effort**: High
**Status**: Proposed

## Problem Statement

instructor-php has no support for the Model Context Protocol (MCP), an open standard for connecting AI assistants to external data sources and tools. This means:
1. Cannot consume tools from MCP servers
2. Cannot expose agent tools as MCP services
3. No integration with growing MCP ecosystem
4. Missing standardized tool discovery and invocation

## Current State

```php
// Current: Tools must be implemented directly in PHP
class SearchTool implements ToolInterface {
    public function name(): string { return 'search'; }
    public function description(): string { return 'Search the web'; }
    public function toToolSchema(): array { ... }
    public function use(mixed ...$args): Result { ... }
}

// No way to consume external MCP tools
// No way to expose tools to MCP clients
```

**Limitations**:
1. Cannot use MCP-compatible tool servers
2. Cannot share tools with other MCP-enabled agents
3. No dynamic tool discovery from external sources
4. Each integration requires custom implementation

## Background: What is MCP?

Model Context Protocol (MCP) is an open protocol that enables:
- **Tool Servers**: Expose tools via JSON-RPC over stdio/HTTP
- **Tool Clients**: Consume tools from any MCP server
- **Dynamic Discovery**: Query available tools at runtime
- **Standardized Schema**: JSON Schema-based tool definitions

```
┌─────────────────┐      MCP Protocol       ┌─────────────────┐
│   MCP Client    │ ◄───────────────────► │   MCP Server    │
│  (AI Agent)     │     (JSON-RPC/stdio)    │ (Tool Provider) │
└─────────────────┘                         └─────────────────┘

Example MCP Servers:
- Filesystem access
- Database queries
- Web browsing
- GitHub API
- Slack integration
```

## Proposed Solution

### MCP Client (Consume External Tools)

```php
// Connect to an MCP server and use its tools
use Instructor\MCP\MCPClient;
use Instructor\MCP\Transport\StdioTransport;
use Instructor\MCP\Transport\HttpTransport;

// Stdio transport (local process)
$filesystemClient = new MCPClient(
    transport: new StdioTransport(
        command: 'npx',
        args: ['-y', '@modelcontextprotocol/server-filesystem', '/path/to/dir'],
    )
);

// HTTP transport (remote server)
$apiClient = new MCPClient(
    transport: new HttpTransport(
        baseUrl: 'http://localhost:3000/mcp',
        headers: ['Authorization' => 'Bearer token'],
    )
);

// Get available tools
$tools = $filesystemClient->listTools();
// Returns: ['read_file', 'write_file', 'list_directory', ...]

// Use with agent
$agent = new Agent(
    driver: $driver,
    tools: new Tools(
        ...$filesystemClient->asTools(),  // MCP tools
        new SearchTool(),                   // Native tools
    ),
);
```

### MCP Tool Adapter

```php
class MCPToolAdapter implements ToolInterface {
    public function __construct(
        private MCPClient $client,
        private MCPToolDefinition $definition,
    ) {}

    public function name(): string {
        return $this->definition->name;
    }

    public function description(): string {
        return $this->definition->description;
    }

    public function toToolSchema(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->definition->name,
                'description' => $this->definition->description,
                'parameters' => $this->definition->inputSchema,
            ],
        ];
    }

    public function use(mixed ...$args): Result {
        try {
            $response = $this->client->callTool(
                name: $this->definition->name,
                arguments: $args,
            );

            return Result::success($response->content);
        } catch (MCPException $e) {
            return Result::failure($e);
        }
    }
}
```

### MCP Client Implementation

```php
class MCPClient {
    private TransportInterface $transport;
    private ?array $toolsCache = null;

    public function __construct(TransportInterface $transport) {
        $this->transport = $transport;
    }

    public function initialize(): InitializeResult {
        return $this->request('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [
                'tools' => [],
            ],
            'clientInfo' => [
                'name' => 'instructor-php',
                'version' => '1.0.0',
            ],
        ]);
    }

    public function listTools(): array {
        if ($this->toolsCache !== null) {
            return $this->toolsCache;
        }

        $response = $this->request('tools/list', []);
        $this->toolsCache = array_map(
            fn($tool) => new MCPToolDefinition(
                name: $tool['name'],
                description: $tool['description'] ?? '',
                inputSchema: $tool['inputSchema'] ?? [],
            ),
            $response['tools']
        );

        return $this->toolsCache;
    }

    public function callTool(string $name, array $arguments): CallToolResult {
        $response = $this->request('tools/call', [
            'name' => $name,
            'arguments' => $arguments,
        ]);

        return new CallToolResult(
            content: $response['content'],
            isError: $response['isError'] ?? false,
        );
    }

    public function asTools(): array {
        return array_map(
            fn($def) => new MCPToolAdapter($this, $def),
            $this->listTools()
        );
    }

    private function request(string $method, array $params): array {
        $message = [
            'jsonrpc' => '2.0',
            'id' => $this->generateId(),
            'method' => $method,
            'params' => $params,
        ];

        return $this->transport->send($message);
    }
}
```

### Transport Implementations

```php
interface TransportInterface {
    public function send(array $message): array;
    public function close(): void;
}

class StdioTransport implements TransportInterface {
    private $process;
    private $stdin;
    private $stdout;

    public function __construct(
        string $command,
        array $args = [],
        ?string $workingDir = null,
    ) {
        $this->process = proc_open(
            [$command, ...$args],
            [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w'],  // stderr
            ],
            $pipes,
            $workingDir
        );

        $this->stdin = $pipes[0];
        $this->stdout = $pipes[1];
    }

    public function send(array $message): array {
        $json = json_encode($message);
        fwrite($this->stdin, $json . "\n");
        fflush($this->stdin);

        $response = fgets($this->stdout);
        return json_decode($response, true);
    }

    public function close(): void {
        fclose($this->stdin);
        fclose($this->stdout);
        proc_close($this->process);
    }
}

class HttpTransport implements TransportInterface {
    public function __construct(
        private string $baseUrl,
        private array $headers = [],
        private ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? new HttpClient();
    }

    public function send(array $message): array {
        $response = $this->httpClient->post(
            $this->baseUrl,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    ...$this->headers,
                ],
                'json' => $message,
            ]
        );

        return json_decode($response->getBody(), true);
    }

    public function close(): void {
        // HTTP is stateless, nothing to close
    }
}
```

### MCP Server (Expose Tools)

```php
// Expose instructor-php tools as MCP server
use Instructor\MCP\MCPServer;
use Instructor\MCP\Transport\StdioServerTransport;

$server = new MCPServer(
    name: 'my-tools-server',
    version: '1.0.0',
);

// Register tools
$server->addTool($searchTool);
$server->addTool($analyzeTool);

// Or add all from a Tools collection
$server->addTools($tools);

// Start server
$server->serve(new StdioServerTransport());
```

### MCP Server Implementation

```php
class MCPServer {
    private array $tools = [];
    private string $name;
    private string $version;

    public function __construct(string $name, string $version) {
        $this->name = $name;
        $this->version = $version;
    }

    public function addTool(ToolInterface $tool): self {
        $this->tools[$tool->name()] = $tool;
        return $this;
    }

    public function addTools(Tools $tools): self {
        foreach ($tools as $tool) {
            $this->addTool($tool);
        }
        return $this;
    }

    public function serve(ServerTransportInterface $transport): void {
        while (true) {
            $message = $transport->receive();
            $response = $this->handleMessage($message);
            $transport->send($response);
        }
    }

    private function handleMessage(array $message): array {
        $method = $message['method'] ?? '';
        $params = $message['params'] ?? [];
        $id = $message['id'];

        $result = match ($method) {
            'initialize' => $this->handleInitialize($params),
            'tools/list' => $this->handleListTools(),
            'tools/call' => $this->handleCallTool($params),
            default => throw new MCPException("Unknown method: $method"),
        };

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    private function handleInitialize(array $params): array {
        return [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [
                'tools' => [],
            ],
            'serverInfo' => [
                'name' => $this->name,
                'version' => $this->version,
            ],
        ];
    }

    private function handleListTools(): array {
        $tools = [];
        foreach ($this->tools as $tool) {
            $schema = $tool->toToolSchema();
            $tools[] = [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'inputSchema' => $schema['function']['parameters'] ?? [],
            ];
        }
        return ['tools' => $tools];
    }

    private function handleCallTool(array $params): array {
        $name = $params['name'];
        $arguments = $params['arguments'] ?? [];

        if (!isset($this->tools[$name])) {
            return [
                'content' => [['type' => 'text', 'text' => "Unknown tool: $name"]],
                'isError' => true,
            ];
        }

        $tool = $this->tools[$name];
        $result = $tool->use(...$arguments);

        if ($result->isSuccess()) {
            return [
                'content' => [['type' => 'text', 'text' => json_encode($result->value())]],
                'isError' => false,
            ];
        }

        return [
            'content' => [['type' => 'text', 'text' => $result->error()]],
            'isError' => true,
        ];
    }
}
```

### Dynamic Tool Discovery

```php
// Discover and connect to multiple MCP servers
class MCPRegistry {
    private array $clients = [];

    public function connect(string $name, MCPClient $client): self {
        $client->initialize();
        $this->clients[$name] = $client;
        return $this;
    }

    public function disconnect(string $name): self {
        if (isset($this->clients[$name])) {
            $this->clients[$name]->close();
            unset($this->clients[$name]);
        }
        return $this;
    }

    public function allTools(): array {
        $tools = [];
        foreach ($this->clients as $name => $client) {
            $clientTools = $client->asTools();
            // Prefix with server name for disambiguation
            foreach ($clientTools as $tool) {
                $tools[] = new PrefixedTool($tool, "{$name}__");
            }
        }
        return $tools;
    }

    public function getClient(string $name): MCPClient {
        return $this->clients[$name] ?? throw new MCPException("Unknown client: $name");
    }
}

// Usage
$registry = new MCPRegistry();
$registry
    ->connect('filesystem', $filesystemClient)
    ->connect('github', $githubClient)
    ->connect('database', $databaseClient);

$agent = new Agent(
    driver: $driver,
    tools: new Tools(...$registry->allTools()),
);
```

## How Other Libraries Implement This

### Claude Desktop / Anthropic MCP SDK

**Location**: `@modelcontextprotocol/sdk`

```typescript
// MCP TypeScript SDK (reference implementation)
import { Client } from "@modelcontextprotocol/sdk/client/index.js";
import { StdioClientTransport } from "@modelcontextprotocol/sdk/client/stdio.js";

const transport = new StdioClientTransport({
    command: "npx",
    args: ["-y", "@modelcontextprotocol/server-filesystem", "/tmp"],
});

const client = new Client(
    { name: "my-client", version: "1.0.0" },
    { capabilities: {} }
);

await client.connect(transport);

// List tools
const { tools } = await client.listTools();

// Call tool
const result = await client.callTool({
    name: "read_file",
    arguments: { path: "/tmp/test.txt" },
});
```

**Key Implementation Details**:
1. Official SDK for TypeScript/JavaScript
2. Supports stdio and SSE transports
3. Full protocol implementation
4. Used by Claude Desktop

### LangChain MCP Adapter

**Location**: `langchain-mcp-adapter`

```python
from langchain_mcp_adapter import MCPToolkit

# Connect to MCP servers
toolkit = MCPToolkit(servers=[
    {"command": "npx", "args": ["-y", "@modelcontextprotocol/server-filesystem"]},
    {"command": "npx", "args": ["-y", "@modelcontextprotocol/server-github"]},
])

# Get LangChain tools
tools = toolkit.get_tools()

# Use with agent
agent = create_tool_calling_agent(llm, tools, prompt)
```

**Key Implementation Details**:
1. Bridges MCP to LangChain tools
2. Supports multiple servers
3. Automatic schema conversion
4. Async support

### Vercel AI SDK MCP

**Location**: `@ai-sdk/mcp`

```typescript
import { experimental_createMCPClient } from 'ai/mcp';

const client = await experimental_createMCPClient({
    transport: {
        type: 'stdio',
        command: 'npx',
        args: ['-y', '@modelcontextprotocol/server-filesystem'],
    },
});

// Get tools for use with AI SDK
const tools = await client.tools();

// Use in generateText
const result = await generateText({
    model: openai('gpt-4o'),
    tools,
    prompt: 'List files in current directory',
});
```

**Key Implementation Details**:
1. Native AI SDK integration
2. Experimental API (evolving)
3. TypeScript type generation
4. Works with all AI SDK models

## Implementation Considerations

### Error Handling

```php
class MCPClient {
    public function callTool(string $name, array $arguments): CallToolResult {
        try {
            $response = $this->transport->send([...]);

            if (isset($response['error'])) {
                throw new MCPErrorException(
                    $response['error']['message'],
                    $response['error']['code']
                );
            }

            return new CallToolResult(...);
        } catch (TransportException $e) {
            throw new MCPConnectionException(
                "Failed to connect to MCP server: {$e->getMessage()}",
                previous: $e
            );
        }
    }
}

// Graceful fallback
class MCPToolAdapter implements ToolInterface {
    public function use(mixed ...$args): Result {
        try {
            $response = $this->client->callTool(...);
            return Result::success($response->content);
        } catch (MCPConnectionException $e) {
            // Log and return error
            return Result::failure(new ToolUnavailableException(
                "MCP tool '{$this->name()}' is temporarily unavailable"
            ));
        }
    }
}
```

### Connection Lifecycle

```php
class MCPClientManager {
    private array $clients = [];

    public function get(string $key): MCPClient {
        if (!isset($this->clients[$key])) {
            throw new MCPException("Client not found: $key");
        }

        $client = $this->clients[$key];

        // Check if still connected
        if (!$client->isConnected()) {
            $client->reconnect();
        }

        return $client;
    }

    public function closeAll(): void {
        foreach ($this->clients as $client) {
            $client->close();
        }
        $this->clients = [];
    }

    // Use with register_shutdown_function or framework lifecycle
    public function __destruct() {
        $this->closeAll();
    }
}
```

### Tool Schema Conversion

```php
class MCPSchemaConverter {
    public static function toOpenAITool(MCPToolDefinition $mcp): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $mcp->name,
                'description' => $mcp->description,
                'parameters' => self::convertJsonSchema($mcp->inputSchema),
            ],
        ];
    }

    private static function convertJsonSchema(array $schema): array {
        // MCP uses standard JSON Schema
        // OpenAI expects JSON Schema subset
        // Handle any incompatibilities

        // Remove unsupported keywords
        unset($schema['$schema']);
        unset($schema['$id']);

        return $schema;
    }
}
```

### Async Tool Execution

```php
// For non-blocking tool execution
class AsyncMCPClient {
    public function callToolAsync(string $name, array $arguments): Promise {
        return async(function () use ($name, $arguments) {
            return $this->client->callTool($name, $arguments);
        });
    }
}

// Parallel MCP tool calls
$promises = [];
foreach ($toolCalls as $call) {
    $client = $this->registry->getClientForTool($call->name());
    $promises[$call->id()] = $client->callToolAsync(
        $call->name(),
        $call->args()
    );
}

$results = await(all($promises));
```

## Migration Path

1. **Phase 1**: Define MCP protocol types (messages, results)
2. **Phase 2**: Implement StdioTransport for local servers
3. **Phase 3**: Create MCPClient with listTools/callTool
4. **Phase 4**: Create MCPToolAdapter to bridge to ToolInterface
5. **Phase 5**: Implement HttpTransport for remote servers
6. **Phase 6**: Add MCPServer for exposing tools
7. **Phase 7**: Create MCPRegistry for multi-server management
8. **Phase 8**: Add async support for parallel execution

## Success Metrics

- [ ] Can connect to MCP servers via stdio
- [ ] Can connect to MCP servers via HTTP
- [ ] MCP tools work seamlessly with Agent
- [ ] Can expose instructor-php tools as MCP server
- [ ] Multiple MCP servers supported simultaneously
- [ ] Proper error handling and reconnection
- [ ] Tool schemas correctly converted

## Open Questions

1. Should we bundle common MCP servers (filesystem, etc.)?
2. How to handle MCP server versioning/compatibility?
3. Should we support MCP resources (not just tools)?
4. How to integrate with PHP async (Revolt, ReactPHP)?
5. Should MCP tools support streaming responses?
