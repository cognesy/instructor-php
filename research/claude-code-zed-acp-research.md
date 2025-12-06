# Claude Code in Zed Editor & Agent Client Protocol (ACP) Research

**Research Date:** December 4, 2025
**Researched by:** Claude Code (Sonnet 4.5)

---

## Executive Summary

This document contains comprehensive research on:
1. The Claude Code extension for Zed editor
2. Technical internals of the Agent Client Protocol (ACP)
3. How to use Claude MAX subscription with Zed

---

## Part 1: Claude Code Extension for Zed

### Overview

Zed's Claude Code integration became available in **public beta in September 2025**, running natively in Zed through their new Agent Client Protocol (ACP). This integration is available on both preview and stable versions.

### Installation & Setup

**No manual installation required!** Zed has native Claude Code support built-in.

#### Setup Steps:

1. **Open Agent Panel**: Press `cmd-?` (macOS) or `ctrl-?` (Windows/Linux)
2. **Create Claude Code Thread**: Click the `+` button and select "Claude Code"
3. **First Run**: Zed automatically installs `@zed-industries/claude-code-acp` adapter
4. **Authenticate**: Run `/login` and choose:
   - API key authentication, OR
   - **"Log in with Claude Code"** to use your Claude Max/Pro subscription

### Using Your Claude MAX Subscription

**Critical Configuration:**

To use your Claude MAX subscription (instead of API credits):

- **Remove/unset** the `ANTHROPIC_API_KEY` environment variable
- If this variable is set, Claude Code will use API credits instead of your subscription
- Use the "Log in with Claude Code" option when authenticating in Zed

**Important:** Claude Code prioritizes environment variable API keys over authenticated subscriptions. This can result in unexpected API charges if not configured properly.

### How It Works

- Zed uses the **Agent Client Protocol (ACP)** to integrate Claude Code natively
- Zed manages its own version of the Claude Code adapter (auto-updates)
- Your local Claude Code CLI installation is **separate** - Zed uses its own managed version
- You can override by setting `CLAUDE_CODE_EXECUTABLE` environment variable if needed

### Key Features in Zed

- Real-time editing across multiple files with syntax highlighting
- Language server support and granular review of code changes
- Multi-buffer editing where users can accept/reject individual code hunks
- Sidebar keeps Claude Code's task list visible
- Custom workflows using slash commands
- Side-by-side with other AI agents (Gemini CLI, etc.)

### Current Limitations (as of September 2025)

- Plan mode was being added in the coming days
- Many built-in slash commands not yet supported by the SDK
- More advanced capabilities being added as Anthropic expands SDK support

---

## Part 2: Agent Client Protocol (ACP) - Technical Deep Dive

### The LSP Analogy

ACP is essentially **LSP for AI coding agents**. Just like LSP decoupled language servers from editors, ACP decouples AI agents from IDEs. Same client-server architecture, same JSON-RPC 2.0 transport, same stdin/stdout pipes.

### Transport Layer

**Process Model:**
- Editor spawns agent as a subprocess
- Bidirectional JSON-RPC 2.0 over stdio pipes
- **Newline-delimited JSON** (each message is a single line)
- Both sides can issue requests AND notifications

**Key difference from HTTP-based protocols:** No connection overhead, no port management, simpler lifecycle tied to subprocess.

```
Editor Process ──stdin/stdout pipes──> Agent Process
   (Client)        JSON-RPC 2.0          (Server)
```

### Protocol Stack (Bottom-Up)

```
┌─────────────────────────────────────┐
│ Application Layer                   │  Your agent/editor logic
├─────────────────────────────────────┤
│ Session Layer                       │  Conversation contexts
├─────────────────────────────────────┤
│ Connection Layer                    │  init, auth, session mgmt
├─────────────────────────────────────┤
│ Protocol Layer                      │  JSON-RPC 2.0 handling
├─────────────────────────────────────┤
│ Transport Layer                     │  Newline-delimited JSON/stdio
└─────────────────────────────────────┘
```

### Session Lifecycle

**1. Initialization Phase:**
```
Client → Agent: initialize(client_info, capabilities)
Agent → Client: {server_info, capabilities, protocol_version}
```

**2. Optional Auth:**
```
Client → Agent: authenticate(credentials)
Agent → Client: {success/failure}
```

**3. Session Creation:**
```
Client → Agent: session/new()
Agent → Client: {session_id, ...}

OR

Client → Agent: session/load(session_id)
Agent → Client: {restored session state}
```

**4. Prompt Turn:**
```
Client → Agent: session/prompt(session_id, messages[])
Agent → Client: streaming session/update notifications
Agent → Client: session/prompt response with stop_reason
```

### Core JSON-RPC Methods

#### Agent-Side Methods (Client → Agent)

**Required:**
- `initialize` - Capability negotiation
- `authenticate` - Auth handshake
- `session/new` - Create new conversation
- `session/prompt` - Send user message

**Optional:**
- `session/load` - Resume previous session (if agent supports persistence)
- `session/set_mode` - Switch agent modes (e.g., plan mode, code mode)

**Notifications (no response expected):**
- `session/cancel` - Interrupt current processing

#### Client-Side Methods (Agent → Client)

**Required:**
- `session/request_permission` - Ask user approval for sensitive operations

**Optional File System:**
- `fs/read_text_file(path)` - Agent requests file read
- `fs/write_text_file(path, content)` - Agent requests file write

**Optional Terminal:**
- `terminal/create(command)`
- `terminal/output(terminal_id)`
- `terminal/wait_for_exit(terminal_id)`
- `terminal/kill(terminal_id)`
- `terminal/release(terminal_id)`

**Notifications:**
- `session/update` - **This is the streaming mechanism**

### Streaming Architecture

Unlike synchronous request-response, ACP uses **server-sent notifications** for streaming:

```
Client sends: session/prompt(session_id, "refactor this")

Agent streams back multiple session/update notifications:
  ├─ message chunk (thought: "analyzing code...")
  ├─ message chunk (thought: "identifying patterns...")
  ├─ tool call (read_file)
  ├─ tool result (file contents)
  ├─ message chunk (agent: "Here's the refactoring...")
  └─ plan update (steps 1-5)

Agent finally responds to original prompt with stop_reason
```

**Session Update Payload Types:**
- Message chunks (`agent`, `user`, `thought` roles)
- Tool calls and results
- Plans (multi-step task breakdowns)
- Available commands (dynamic slash commands)
- Mode changes (plan → code → review)

This is **token-by-token streaming** – the editor can render partial outputs as the LLM generates them.

### Permission Model

Critical difference from autonomous agents: **User-gated operations**

```
Agent wants to write file:
1. Agent → Client: session/request_permission(tool_call_details)
2. Client shows UI to user: "Allow agent to write foo.ts?"
3. Client → Agent: {approved: true/false}
4. Agent proceeds or aborts
```

This keeps the user in control, unlike autonomous agents that can wreak havoc.

### MCP Reuse Strategy

ACP **consciously reuses MCP (Model Context Protocol) data types**:
- Text content structures
- Code diff formats
- Tool result schemas

**Why?** Don't reinvent the wheel. MCP already solved serialization for LLM I/O.

**Architecture:**
```
┌──────────────────────────────────────┐
│  Agent Code (any framework)          │
├──────────────────────────────────────┤
│  ACP Server (message handling)       │
│    ├─ Uses MCP types internally      │
│    └─ Exposes JSON-RPC interface     │
├──────────────────────────────────────┤
│  JSON-RPC 2.0 / stdio                │
└──────────────────────────────────────┘
```

### Multi-Agent Architecture

ACP supports **multiple concurrent sessions**:

```
Editor Client
  ├─ Session A (Claude Code agent)
  ├─ Session B (Gemini CLI agent)
  └─ Session C (Custom agent)
```

Each session has independent state, but all share the same stdio transport.

### Protocol Constraints

**Strict rules:**
- All file paths **MUST be absolute** (no relative paths)
- Line numbers are **1-indexed** (not 0-indexed)
- Errors use standard JSON-RPC 2.0 error objects (`code`, `message`)

### Extensibility

**Two mechanisms:**

1. **Underscore-prefixed methods** for custom extensions:
   ```json
   {"jsonrpc": "2.0", "method": "_custom/my_feature", ...}
   ```

2. **`_meta` fields** for arbitrary metadata:
   ```json
   {
     "content": "...",
     "_meta": {
       "custom_field": "value"
     }
   }
   ```

Both maintain backward compatibility – clients/agents can ignore unknown extensions.

### Claude Code + Zed Implementation

Zed built an **adapter** (`claude-code-acp`):

```
Zed Editor (ACP Client)
    ↓ JSON-RPC over stdio
claude-code-acp Adapter
    ↓ Translates to/from
Claude Code SDK
    ↓ HTTP to Anthropic API
Claude API
```

**Key insight:** The adapter wraps Claude Code's SDK and **translates** its interactions into ACP's JSON-RPC format. Claude Code doesn't speak ACP natively – the adapter is a protocol translator.

### Comparison to Other Protocols

| Protocol | Scope | Transport | Use Case |
|----------|-------|-----------|----------|
| **LSP** | Language features | JSON-RPC/stdio | Autocomplete, linting |
| **MCP** | Tool/context access | Various | LLM tool calling |
| **ACP** | Agentic editing | JSON-RPC/stdio | AI code generation |
| **DAP** | Debugging | JSON-RPC | Debugger UI |

ACP is **narrowly scoped** to coding agents, unlike MCP which is general-purpose LLM tooling.

### Implementation Resources

**SDKs:**
- **Rust:** `agent-client-protocol` crate
- **TypeScript:** `@zed-industries/agent-client-protocol` (npm)
  - Uses Zod for runtime schema validation
  - Exports `AgentSideConnection` and `ClientSideConnection`
- **Python:** Available but less mature
- **Kotlin:** `acp-kotlin`

**Schema:** `/schema/schema.json` in the [GitHub repo](https://github.com/zed-industries/agent-client-protocol) defines the full spec

**License:** Apache 2.0 (open source)

### The Big Picture

ACP solves the **editor lock-in problem** for AI agents:

- **Before:** Every agent (Claude Code, Cursor, Copilot) builds custom IDE integrations
- **After:** One agent → many editors, one editor → many agents

Just like LSP killed the N×M problem for language servers.

---

## Technical Specifications Summary

### JSON-RPC Foundation

The protocol implements JSON-RPC 2.0 with two message types:
- **Methods**: Request-response pairs expecting results or errors
- **Notifications**: One-way messages without responses

### Core Message Flow

1. **Initialization**: Client sends `initialize` to negotiate versions and capabilities, optionally followed by `authenticate`
2. **Session Management**: Either `session/new` creates a fresh session or `session/load` resumes existing ones
3. **Prompt Turn**: Client transmits `session/prompt` with user input; Agent responds with `session/prompt` containing stop reasons

### Protocol Requirements

- All file paths in the protocol **MUST** be absolute
- Line numbers follow 1-based indexing
- Responses include `result` fields on success
- Errors contain `error` objects with `code` and `message` fields per JSON-RPC 2.0

---

## Sources

### Claude Code in Zed
- [Claude Code: Now in Beta in Zed — Zed's Blog](https://zed.dev/blog/claude-code-via-acp)
- [External Agents | Zed Code Editor Documentation](https://zed.dev/docs/ai/external-agents)
- [Using Claude Code with your Pro or Max plan | Claude Help Center](https://support.claude.com/en/articles/11145838-using-claude-code-with-your-pro-or-max-plan)
- [Managing API Key Environment Variables in Claude Code | Claude Help Center](https://support.claude.com/en/articles/12304248-managing-api-key-environment-variables-in-claude-code)
- [Claude Code - ACP Agent | Zed](https://zed.dev/acp/agent/claude-code)
- [GitHub - jiahaoxiang2000/claude-code-zed](https://github.com/jiahaoxiang2000/claude-code-zed)
- [GitHub - zed-industries/claude-code-acp](https://github.com/zed-industries/claude-code-acp)

### Agent Client Protocol
- [Agent Client Protocol Overview](https://agentclientprotocol.com/protocol/overview)
- [Zed's ACP GitHub Repository](https://github.com/zed-industries/agent-client-protocol)
- [Agent Client Protocol: The LSP for AI Coding Agents](https://blog.promptlayer.com/agent-client-protocol-the-lsp-for-ai-coding-agents/)
- [Intro to Agent Client Protocol (ACP)](https://block.github.io/goose/blog/2025/10/24/intro-to-agent-client-protocol-acp/)
- [@zed-industries/agent-client-protocol npm package](https://www.npmjs.com/package/@zed-industries/agent-client-protocol)
- [Introduction - Agent Client Protocol](https://agentclientprotocol.com/overview/introduction)
- [Bring Your Own Agent to Zed — Featuring Gemini CLI](https://zed.dev/blog/bring-your-own-agent-to-zed)

### Architecture & Technical Deep Dives
- [Architecture - Agent Communication Protocol](https://agentcommunicationprotocol.dev/core-concepts/architecture)
- [Agent Client Protocol (ACP) Explained: Plug-and-Play AI Agents for Code Editors](https://codestandup.com/posts/2025/agent-client-protocol-acp-explained/)
- [A Deep Technical Dive into Next-Generation Interoperability Protocols - MarkTechPost](https://www.marktechpost.com/2025/05/09/a-deep-technical-dive-into-next-generation-interoperability-protocols-model-context-protocol-mcp-agent-communication-protocol-acp-agent-to-agent-protocol-a2a-and-agent-network-protocol-anp/)

---

## Conclusion

The Agent Client Protocol represents a significant step forward in making AI coding assistants interoperable across different editors. Similar to how LSP standardized language server integration, ACP provides an open standard that allows any AI agent to work with any compatible editor.

For developers using Zed with Claude Code, the integration is seamless and requires no manual installation. The key consideration is ensuring proper authentication configuration to use Claude MAX/Pro subscriptions instead of incurring API charges.

The protocol's design—using JSON-RPC 2.0 over stdio, supporting streaming updates, and implementing user-gated permissions—makes it a practical and secure solution for AI-assisted coding workflows.
