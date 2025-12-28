# Model Context Protocol

Model Context Protocol (MCP) is a protocol for connecting models to additional tools and context. It's a great option for you to provide Codex access to documentation for different libraries or have it interact with some of your other developer tools like your browser or Figma.

MCP servers are supported by both the Codex CLI and the Codex IDE extension.

## Supported MCP features

- STDIO servers (servers that can be launched via a command on your computer)
  - Environment variables
- Streamable HTTP servers (servers that can be accessed via a URL)
  - Bearer token authentication
  - OAuth authentication (use `codex mcp login <server-name>` for servers that support OAuth)

## Connect Codex to a MCP server

MCP configuration for Codex is stored within the `~/.codex/config.toml` configuration file alongside other Codex configuration options.

Configuration is shared between the CLI and the IDE extension. So once you have configured your MCP servers, you can seamlessly switch between the two Codex clients.

To configure your MCP servers, you have two options:

1. **Using the CLI**: If you have the Codex CLI installed, you can use the `codex mcp` command to configure your MCP servers.
2. **Modifying the config file directly**: Alternatively, you can modify the `config.toml` file directly.

### Configuration - CLI

#### Add a MCP server

```bash
codex mcp add <server-name> --env VAR1=VALUE1 --env VAR2=VALUE2 -- <stdio server-command>
```

For example, to add Context7 (a free MCP server for developer documentation), you can run the following command:

```bash
codex mcp add context7 -- npx -y @upstash/context7-mcp
```

#### Other CLI commands

To see all available MCP commands, you can run `codex mcp --help`.

#### Terminal UI (TUI)

Once you have launched `codex` and are running the TUI, you can use `/mcp` to see your actively connected MCP servers.

### Configuration - config.toml

For more fine grained control over MCP server options, you can manually edit the `~/.codex/config.toml` configuration file. If you are using the IDE extension, you can find the config file by clicking the gear icon in the top right corner of the extension and then clicking `MCP settings > Open config.toml`.

Each MCP server is configured with a `[mcp_servers.<server-name>]` table in the config file.

#### STDIO servers

- `command` - [Required] The command to launch the server
- `args` - [Optional] The arguments to pass to the server
- `env` - [Optional] The environment variables to set for the server
- `env_vars` - [Optional] Additional environment variables to whitelist/forward
- `cwd` - [Optional] Working directory to launch the server from

#### Streamable HTTP servers

- `url` - [Required] The URL to access the server
- `bearer_token_env_var` - [Optional] Name of env var containing a bearer token to send in `Authorization`
- `http_headers` - [Optional] Map of header names to static values
- `env_http_headers` - [Optional] Map of header names to env var names (values pulled from env)

#### Other configuration options

- `startup_timeout_sec` - [Optional] The timeout in seconds for the server to start
- `tool_timeout_sec` - [Optional] The timeout in seconds for the server to execute a tool
- (defaults: `startup_timeout_sec = 10`, `tool_timeout_sec = 60`)
- `enabled` - [Optional] Set `false` to disable a configured server without deleting it
- `enabled_tools` - [Optional] Allow-list of tools to expose from the server
- `disabled_tools` - [Optional] Deny-list of tools to hide (applied after `enabled_tools`)

#### `config.toml` Examples

```toml
[mcp_servers.context7]
command = "npx"
args = ["-y", "@upstash/context7-mcp"]

[mcp_servers.context7.env]
MY_ENV_VAR = "MY_ENV_VALUE"
```

```toml
[mcp_servers.figma]
url = "https://mcp.figma.com/mcp"
bearer_token_env_var = "FIGMA_OAUTH_TOKEN"
http_headers = { "X-Figma-Region" = "us-east-1" }
```

```toml
[mcp_servers.chrome_devtools]
url = "http://localhost:3000/mcp"
enabled_tools = ["open", "screenshot"]
disabled_tools = ["screenshot"] # applied after enabled_tools
startup_timeout_sec = 20
tool_timeout_sec = 45
enabled = true
```

## Examples of useful MCPs

There is an ever growing list of useful MCP servers that can be helpful while you are working with Codex.

Some of the most common MCPs we've seen are:

- [Context7](https://github.com/upstash/context7) — connect to a wide range of up-to-date developer documentation
- Figma [Local](https://developers.figma.com/docs/figma-mcp-server/local-server-installation/) and [Remote](https://developers.figma.com/docs/figma-mcp-server/remote-server-installation/) - access to your Figma designs
- [Playwright](https://www.npmjs.com/package/@playwright/mcp) - control and inspect a browser using Playwright
- [Chrome Developer Tools](https://github.com/ChromeDevTools/chrome-devtools-mcp/) — control and inspect a Chrome browser
- [Sentry](https://docs.sentry.io/product/sentry-mcp/#codex) — access to your Sentry logs
- [GitHub](https://github.com/github/github-mcp-server) — Control over your GitHub account beyond what git allows (like controlling PRs, issues, etc.)

## Running Codex as an MCP server

Additionally, to connect Codex to MCP servers, you can also run Codex as an MCP server. This way you can connect it to other MCP clients such as an agent you are building using the [OpenAI Agents SDK](https://openai.github.io/openai-agents-js/guides/mcp/).

To start Codex as an MCP server, you can use the following command:

```bash
codex mcp-server
```

You can launch a Codex MCP server with the [Model Context Protocol Inspector](https://modelcontextprotocol.io/legacy/tools/inspector):

```bash
npx @modelcontextprotocol/inspector codex mcp-server
```

Send a `tools/list` request and you will see that there are two tools available:

**`codex`** - Run a Codex session. Accepts configuration parameters matching the Codex Config struct. The `codex` tool takes the following properties:

| Property                | Type    | Description                                                                                                                                            |
| ----------------------- | ------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **`prompt`** (required) | string  | The initial user prompt to start the Codex conversation.                                                                                               |
| `approval-policy`       | string  | Approval policy for shell commands generated by the model: `untrusted`, `on-failure`, `never`.                                                         |
| `base-instructions`     | string  | The set of instructions to use instead of the default ones.                                                                                            |
| `config`                | object  | Individual [config settings](https://github.com/openai/codex/blob/main/docs/config.md#config) that will override what is in `$CODEX_HOME/config.toml`. |
| `cwd`                   | string  | Working directory for the session. If relative, resolved against the server process's current directory.                                               |
| `include-plan-tool`     | boolean | Whether to include the plan tool in the conversation.                                                                                                  |
| `model`                 | string  | Optional override for the model name (e.g. `o3`, `o4-mini`).                                                                                           |
| `profile`               | string  | Configuration profile from `config.toml` to specify default options.                                                                                   |
| `sandbox`               | string  | Sandbox mode: `read-only`, `workspace-write`, or `danger-full-access`.                                                                                 |

**`codex-reply`** - Continue a Codex session by providing the conversation id and prompt. The `codex-reply` tool takes the following properties:

| Property                        | Type   | Description                                              |
| ------------------------------- | ------ | -------------------------------------------------------- |
| **`prompt`** (required)         | string | The next user prompt to continue the Codex conversation. |
| **`conversationId`** (required) | string | The id of the conversation to continue.                  |

### Trying it Out

<DocsTip>
  Codex often takes a few minutes to run. To accommodate this, adjust the MCP
  inspector's Request and Total timeouts to 600000ms (10 minutes) under ⛭
  Configuration.
</DocsTip>

Use the MCP inspector and `codex mcp-server` to build a simple tic-tac-toe game with the following settings:

| Property          | Value                                                                                                                  |
| ----------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `approval-policy` | never                                                                                                                  |
| `sandbox`         | workspace-write                                                                                                        |
| `prompt`          | Implement a simple tic-tac-toe game with HTML, Javascript, and CSS. Write the game in a single file called index.html. |

Click "Run Tool" and you should see a list of events emitted from the Codex MCP server as it builds the game.