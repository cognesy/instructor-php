# Configuring Codex

Codex should work out of the box for most users. But sometimes you want to configure Codex to your own liking to better suit your needs. For this there is a wide range of configuration options.

## Codex configuration file

The configuration file for Codex is located at `~/.codex/config.toml`.

To access the configuration file when you are using the Codex IDE extension, you can click the gear icon in the top right corner of the extension and then clicking `Codex Settings > Open config.toml`.

This configuration file is shared between the CLI and the IDE extension and can be used to configure things like the default model, [approval policies, sandbox settings](/codex/security) or [MCP servers](/codex/mcp) that Codex should have access to.

## High level configuration options

Codex provides a wide range of configuration options. Some of the most commonly changed settings are:

#### Default model

Pick which model Codex uses by default in both the CLI and IDE.

**Using `config.toml`:**

```toml
model = "gpt-5"
```

**Using CLI arguments:**

```shell title="Test"
codex --model gpt-5
```

#### Model provider

Select the backend provider referenced by the active model. Be sure to [define the provider](https://github.com/openai/codex/blob/main/docs/config.md#model_providers) in your config first.

**Using `config.toml`:**

```toml
model_provider = "ollama"
```

**Using CLI arguments:**

```shell
codex --config model_provider="ollama"
```

#### Approval prompts

Control when Codex pauses to ask before running generated commands.

**Using `config.toml`:**

```toml
approval_policy = "on-request"
```

**Using CLI arguments:**

```shell
codex --ask-for-approval on-request
```

#### Sandbox level

Adjust how much filesystem and network access Codex has while executing commands.

**Using `config.toml`:**

```toml
sandbox_mode = "workspace-write"
```

**Using CLI arguments:**

```shell
codex --sandbox workspace-write
```

#### Reasoning depth

Tune how much reasoning effort the model applies when supported.

**Using `config.toml`:**

```toml
model_reasoning_effort = "high"
```

**Using CLI arguments:**

```shell
codex --config model_reasoning_effort="high"
```

#### Command environment

Restrict or expand which environment variables are forwarded to spawned commands.

**Using `config.toml`:**

```toml
[shell_environment_policy]
include_only = ["PATH", "HOME"]
```

**Using CLI arguments:**

```shell
codex --config shell_environment_policy.include_only='["PATH","HOME"]'
```

## Profiles

Profiles bundle a set of configuration values so you can jump between setups without editing `config.toml` each time. They currently apply to the Codex CLI.

Define profiles under `[profiles.<name>]` in `config.toml` and launch the CLI with `codex --profile <name>`:

```toml
model = "gpt-5-codex"
approval_policy = "on-request"

[profiles.deep-review]
model = "gpt-5-pro"
model_reasoning_effort = "high"
approval_policy = "never"

[profiles.lightweight]
model = "gpt-4.1"
approval_policy = "untrusted"
```

Running `codex --profile deep-review` will use the `gpt-5-pro` model with high reasoning effort and no approval policy. Running `codex --profile lightweight` will use the `gpt-4.1` model with untrusted approval policy. To make one profile the default, add `profile = "deep-review"` at the top level of `config.toml`; the CLI will load that profile unless you override it on the command line.

Values resolve in this order: explicit CLI flags (like `--model`) override everything, profile values come next, then root-level entries in `config.toml`, and finally the CLI’s built-in defaults. Use that precedence to layer common settings at the top level while letting each profile tweak just the fields that need to change.

## Feature flags

Optional and experimental capabilities are toggled via the `[features]` table in `config.toml`. If Codex emits a deprecation warning mentioning a legacy key (such as `tools.web_search` or `enable_experimental_windows_sandbox`), move that setting into `[features]` or launch the CLI with `codex --enable <feature>`.

```toml
[features]
unified_exec = true             # enable background terminal sessions
shell_snapshot = true           # speed up repeated commands
web_search_request = true        # allow the model to request web searches
# view_image_tool defaults to true; omit to keep defaults
```

### Supported features

| Key                          | Default | Stage        | Description                                                     |
| ---------------------------- | :-----: | ------------ | --------------------------------------------------------------- |
| `undo`                       |  true   | Stable       | Enable undo via per-turn git ghost snapshots                     |
| `parallel`                   |  true   | Stable       | Allow models that support it to call multiple tools in parallel  |
| `shell_tool`                 |  true   | Stable       | Enable the default `shell` tool                                  |
| `skills`                     |  true   | Experimental | Enable discovery and injection of skills                         |
| `warnings`                   |  true   | Stable       | Send tool-usage warnings to the model                            |
| `view_image_tool`            |  true   | Stable       | Include the `view_image` tool                                    |
| `web_search_request`         |  false  | Stable       | Allow the model to issue web searches                            |
| `unified_exec`               |  false  | Beta         | Use the unified PTY-backed exec tool                             |
| `shell_snapshot`             |  false  | Beta         | Snapshot your shell environment to speed up repeated commands    |
| `apply_patch_freeform`       |  false  | Experimental | Include the freeform `apply_patch` tool                          |
| `exec_policy`                |  true   | Experimental | Enforce exec policy checks for `shell`/`unified_exec`            |
| `experimental_windows_sandbox` |  false | Experimental | Use the Windows restricted-token sandbox                         |
| `elevated_windows_sandbox`   |  false  | Experimental | Use the elevated Windows sandbox pipeline                         |
| `remote_compaction`          |  true   | Experimental | Enable remote compaction (ChatGPT auth only)                     |
| `remote_models`              |  false  | Experimental | Refresh remote model list before showing readiness               |
| `tui2`                       |  false  | Development  | Use the experimental TUI v2 (viewport) implementation            |

<DocsTip>
  <p>
    Omit feature keys to keep their defaults. <br /> Legacy booleans such as{" "}
    <code>tools.web_search</code>, <code>tools.view_image</code>,{" "}
    <code>experimental_use_unified_exec_tool</code>,{" "}
    <code>experimental_use_freeform_apply_patch</code>,{" "}
    <code>include_apply_patch_tool</code>, and{" "}
    <code>enable_experimental_windows_sandbox</code> are deprecated—migrate them
    to the matching <code>[features].&lt;key&gt;</code> flag to avoid repeated warnings.
  </p>
</DocsTip>

### Enabling features quickly

- In `config.toml`: add `feature_name = true` under `[features]`.
- CLI onetime: `codex --enable feature_name`.
- Multiple flags: `codex --enable feature_a --enable feature_b`.
- Disable explicitly by setting the key to `false` in `config.toml`.

## Advanced configuration

### Custom model providers

Define additional providers and point `model_provider` at them:

```toml
model = "gpt-4o"
model_provider = "openai-chat-completions"

[model_providers.openai-chat-completions]
name = "OpenAI using Chat Completions"
base_url = "https://api.openai.com/v1"
env_key = "OPENAI_API_KEY"
wire_api = "chat"
query_params = {}

[model_providers.ollama]
name = "Ollama"
base_url = "http://localhost:11434/v1"

[model_providers.mistral]
name = "Mistral"
base_url = "https://api.mistral.ai/v1"
env_key = "MISTRAL_API_KEY"
```

Add request headers when needed:

```toml
[model_providers.example]
http_headers = { "X-Example-Header" = "example-value" }
env_http_headers = { "X-Example-Features" = "EXAMPLE_FEATURES" }
```

### Azure provider & per-provider tuning

```toml
[model_providers.azure]
name = "Azure"
base_url = "https://YOUR_PROJECT_NAME.openai.azure.com/openai"
env_key = "AZURE_OPENAI_API_KEY"
query_params = { api-version = "2025-04-01-preview" }
wire_api = "responses"

[model_providers.openai]
request_max_retries = 4
stream_max_retries = 10
stream_idle_timeout_ms = 300000
```

### Model reasoning, verbosity, and limits

```toml
model_reasoning_summary = "none"          # disable summaries
model_verbosity = "low"                   # shorten responses on Responses API providers
model_supports_reasoning_summaries = true # force reasoning on custom providers
model_context_window = 128000             # override when Codex doesn't know the window
```

`model_verbosity` applies only to providers using the Responses API; Chat Completions providers will ignore the setting.

### Approval policies and sandbox modes

Pick approval strictness (affects when Codex pauses) and sandbox level (affects file/network access). See [Sandbox & approvals](/codex/security) for deeper examples.

```toml
approval_policy = "untrusted"   # other options: on-request, on-failure, never
sandbox_mode = "workspace-write"

[sandbox_workspace_write]
exclude_tmpdir_env_var = false  # allow $TMPDIR
exclude_slash_tmp = false       # allow /tmp
writable_roots = ["/Users/YOU/.pyenv/shims"]
network_access = false          # opt in to outbound network
```

Disable sandboxing entirely (use only if your environment already isolates processes):

```toml
sandbox_mode = "danger-full-access"
```

### Rules (preview)

A `.rules` file lets you define fine-grained rules that govern Codex's behavior, such as identifying commands that Codex is allowed to run _outside_ the sandbox.

For example, suppose you created the file `~/.codex/rules/default.rules` with the following contents:

```python
# Rule that allows commands that start with `gh pr view` to run outside
# the sandbox for Codex's "shell tool."
prefix_rule(
    # The prefix to match.
    pattern = ["gh", "pr", "view"],

    # The action to take when Codex requests to run a matching command.
    decision = "allow",

    # `match` and `not_match` are optional "inline unit tests" where you can
    # provide examples of commands that should (or should not) match this rule,
    # respectively. The .rules file will fail to load if these tests fail.
    match = [
      "gh pr view 7888",
      "gh pr view --repo openai/codex",
      "gh pr view 7888 --json title,body,comments",
    ],
    not_match = [
      # Does not match because the `pattern` must be an exact prefix.
      "gh pr --repo openai/codex view 7888",
    ],
)
```

A `prefix_rule()` lets you pre-approve, prompt, or block commands before Codex runs them using the following options:

- `pattern` **(required)** is a non-empty list where each element is either a literal (e.g., `"pr"`) or a union of literals (e.g., `["view", "list"]`) that defines the _command prefix_ to be matched by the rule. When Codex's shell tool considers a command to run (which internally can be thought of as a list of arguments for [`execvp(3)`](https://linux.die.net/man/3/execvp)), it will compare the start of the list of arguments with those of the `pattern`.
  - Use a union to express alternatives for an individual argument. For example, `pattern = ["gh", "pr", ["view", "list"]]` would allow both `gh pr view` and `gh pr list` to run outside the sandbox.
- `decision` **(defaults to `"allow"`)** sets the strictness; Codex applies the most restrictive decision when multiple rules match (`forbidden` > `prompt` > `allow`)
  - `allow` means the command should be run automatically outside the sandbox: the user will not be consulted.
  - `prompt` means the user will be prompted to allow each individual invocation of a matching command. If approved, the command will be run outside the sandbox.
  - `forbidden` means the request will be rejected automatically without notifying the user.
- `match` and `not_match` **(defaults to `[]`)** act like tests that Codex validates when it loads your policy.

Codex loads every `*.rules` file under `~/.codex/rules` at startup; when you whitelist a command in the TUI, it appends a rule to `~/.codex/rules/default.rules` so future runs can skip the prompt.

Note the input language for a `.rules` file is [Starlark](https://github.com/bazelbuild/starlark/blob/master/spec.md). Its syntax is similar to Python's, but it is designed to be a safe, embeddable language that can be interpeted without side-effects (such as touching the filesystem). Starlark's affordances such as list comprehensions makes it possible to build up rules dynamically.

Finally, to test how a policy applies to a command without editing files, you can use the CLI helper:

```shell
$ codex execpolicy check --pretty --rules ~/.codex/rules/default.rules -- gh pr view 7888 --json title,body,comments
{
  "matchedRules": [
    {
      "prefixRuleMatch": {
        "matchedPrefix": [
          "gh",
          "pr",
          "view"
        ],
        "decision": "prompt"
      }
    }
  ],
  "decision": "prompt"
}
```

Pass multiple `--rules` flags to combine files and add `--pretty` for formatted JSON. The rules system is still in preview, so syntax and defaults may change.

### Shell environment templates

`shell_environment_policy` controls which environment variables Codex passes to any subprocess it launches (for example, when running a tool-command the model proposes). Start from a clean slate (`inherit = "none"`) or a trimmed set (`inherit = "core"`), then layer on excludes, includes, and overrides to avoid leaking secrets while still providing the paths, keys, or flags your tasks need.

```toml
[shell_environment_policy]
inherit = "none"
set = { PATH = "/usr/bin", MY_FLAG = "1" }
ignore_default_excludes = false
exclude = ["AWS_*", "AZURE_*"]
include_only = ["PATH", "HOME"]
```

Patterns are case-insensitive globs (`*`, `?`, `[A-Z]`); `ignore_default_excludes = false` keeps the automatic KEY/SECRET/TOKEN filter before your includes/excludes run.

### MCP servers

See the dedicated [MCP guide](/codex/mcp) for full server setups and toggle descriptions. Below is a minimal STDIO example using the Context7 MCP server:

```toml
[mcp_servers.context7]
command = "npx"
args = ["-y", "@upstash/context7-mcp"]
```

### Observibility and telemetry

Enable OpenTelemetry (Otel) log export to track Codex runs (API requests, SSE/events, prompts, tool approvals/results). Disabled by default; opt in via `[otel]`:

```toml
[otel]
environment = "staging"   # defaults to "dev"
exporter = "none"         # set to otlp-http or otlp-grpc to send events
log_user_prompt = false   # redact user prompts unless explicitly enabled
```

Choose an exporter:

```toml
[otel]
exporter = { otlp-http = {
  endpoint = "https://otel.example.com/v1/logs",
  protocol = "binary",
  headers = { "x-otlp-api-key" = "${OTLP_TOKEN}" }
}}
```

```toml
[otel]
exporter = { otlp-grpc = {
  endpoint = "https://otel.example.com:4317",
  headers = { "x-otlp-meta" = "abc123" }
}}
```

If `exporter = "none"` Codex records events but sends nothing. Exporters batch asynchronously and flush on shutdown. Event metadata includes service name, CLI version, env tag, conversation id, model, sandbox/approval settings, and per-event fields (see Config reference table below).

### Notifications

Use `notify` to trigger an external program whenever Codex emits supported events (today: `agent-turn-complete`). This is handy for desktop toasts, chat webhooks, CI updates, or any side-channel alerting that the built-in TUI notifications don't cover.

```toml
notify = ["python3", "/path/to/notify.py"]
```

Example `notify.py` (truncated) that reacts to `agent-turn-complete`:

```python
#!/usr/bin/env python3
import json, subprocess, sys

def main() -> int:
    notification = json.loads(sys.argv[1])
    if notification.get("type") != "agent-turn-complete":
        return 0
    title = f"Codex: {notification.get('last-assistant-message', 'Turn Complete!')}"
    message = " ".join(notification.get("input-messages", []))
    subprocess.check_output([
        "terminal-notifier",
        "-title", title,
        "-message", message,
        "-group", "codex-" + notification.get("thread-id", ""),
        "-activate", "com.googlecode.iterm2",
    ])
    return 0

if __name__ == "__main__":
    sys.exit(main())
```

Place the script somewhere on disk and point `notify` to it. For lighter in-terminal alerts, toggle `tui.notifications` instead.

## Personalizing the Codex IDE Extension

Additionally to configuring the underlying Codex agent through your `config.toml` file, you can also configure the way you use the Codex IDE extension.

To see the list of available configuration options, click the gear icon in the top right corner of the extension and then click `IDE settings`.

To define your own keyboard shortcuts to trigger Codex or add something to the Codex context, you can click the gear icon in the top right corner of the extension and then click `Keyboard shortcuts`.

<ConfigTable
  title="Configuration options"
  options={configOptions}
  client:load
/>