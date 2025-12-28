# Codex security guide

Codex is built with a focus on protecting code and data from exfiltration, and guarding against misuse.

By default, the agent runs with network access disabled and edits files restricted to the current workspace, whether locally or in the cloud.

## Agent sandbox

There are different sandboxing methods based on where you're running Codex:

- **Codex Cloud**: Executes in isolated OpenAI-managed containers, preventing access to the user’s host systems or unrelated data. Users can expand access intentionally (e.g. allow dependency installation or specific domains) when required; internet access is always enabled during the setup phase which runs before the agent has access.
- **Codex CLI / IDE extension**: Seatbelt policies on macOS and Linux seccomp + landlock enforce local sandboxing. Defaults include no network access and write permissions limited to the active workspace. Users can configure the sandbox, approval, and network security settings based on their risk tolerance.

We've chosen a powerful default for how Codex works on your computer. In this default approval mode, Codex can read files, make edits, and run commands in the working directory automatically.

However, Codex will need your approval to work outside the working directory or run commands with network access. When you just want to chat, or if you want to plan before diving in, you can switch to `Read Only` mode with the `/approvals` command.

## Network access

You can read about how to enable full or domain-specific allowlist in our [agent internet access](/codex/cloud/internet-access) documentation for Codex Cloud.

Or if you're using Codex CLI / IDE extension, the default `workspace-write` sandbox option will have the network disabled by default, unless enabled in config like this:

```toml
[sandbox_workspace_write]
network_access = true
```

You can also enable the [web search tool](https://platform.openai.com/docs/guides/tools-web-search) without allowing unfettered network access to the agent by passing the `--search` flag or toggling the feature in `config.toml`:

```toml
[features]
web_search_request = true
```

We recommend exercising caution when enabling network access or enabling web search in Codex, due to the risk of prompt injection.

## Defaults and recommendations

- On launch, Codex detects whether the folder is version-controlled and recommends:
  - Version-controlled folders: `Auto` (workspace write + on-request approvals)
  - Non-version-controlled folders: `Read Only`
- The workspace includes the current directory and temporary directories like `/tmp`. Use the `/status` command to see which directories are in the workspace.
- We recommend just using the default where it can read/edit files and run commands sandboxed:
  - `codex`
- You can set these explicitly:
  - `codex --sandbox workspace-write --ask-for-approval on-request`
  - `codex --sandbox read-only --ask-for-approval on-request`

### Can I run Codex without any approvals?

Yes, you can disable all approval prompts with: `--ask-for-approval never` or `-a never` in short-hand.

This option works with all `--sandbox` modes, so you still have full control over Codex's level of autonomy. It will make its best attempt with whatever contraints you provide.

If you need Codex to read files, make edits, and run commands with network access, without approval, you can use `Full Access`. **Exercise caution before doing so.**

### Common sandbox + approvals combinations

| Intent                                                            | Flags                                                          | Effect                                                                                                                            |
| ----------------------------------------------------------------- | -------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| Auto (preset)                                                     | _no flags needed, default_                                     | Codex can read files, make edits, and run commands in the workspace. Codex asks for approval to run commands outside the sandbox. |
| Read-only                                                         | `--sandbox read-only --ask-for-approval never`                 | Codex can only read files; never asks for approval.                                                                               |
| Automatically edit but ask for approval to run untrusted commands | `--sandbox workspace-write --ask-for-approval untrusted`       | Can can read and edit files but will ask for approval before running untrusted commands.                                          |
| Dangerous full access                                             | `--dangerously-bypass-approvals-and-sandbox` (alias: `--yolo`) | No sandbox; no approvals _(not recommended)_                                                                                      |

#### Configuration in `config.toml`

```toml
# always ask for approval mode
approval_policy = "untrusted"
sandbox_mode    = "read-only"

# Optional: allow network in workspace-write mode
[sandbox_workspace_write]
network_access = true
```

### Experimenting with the Codex Sandbox

To test to see what happens when a command is run under the sandbox provided by Codex, we provide the following subcommands in Codex CLI:

```bash
# macOS
codex sandbox macos [COMMAND]...
# Linux
codex sandbox linux [COMMAND]...
```

## OS-level sandboxing

The mechanism Codex uses to implement the sandbox policy depends on your OS:

- **macOS** uses Seatbelt policies and runs commands using `sandbox-exec` with a profile (`-p`) that corresponds to the `--sandbox` that was specified.
- **Linux** uses a combination of Landlock/seccomp APIs to enforce the `sandbox` configuration.

_For Windows users, we recommend running Codex locally in [Windows Subsystem for Linux (WSL)](https://learn.microsoft.com/en-us/windows/wsl/install) or a Docker container to provide secure isolation._

If you use the Codex IDE extension on Windows, WSL is supported directly—set the following in your VS Code settings to keep the agent inside WSL whenever it's available:

```json
{
  "chatgpt.runCodexInWindowsSubsystemForLinux": true
}
```

This ensures the IDE extension inherits Linux sandboxing semantics for commands, approvals, and filesystem access even when the host OS is Windows. Learn more in our [Windows setup guide](/codex/windows).

Note that when running Linux in a containerized environment such as Docker, sandboxing may not work if the host/container configuration does not support the necessary Landlock/seccomp APIs.

In such cases, we recommend configuring your Docker container so that it provides the sandbox guarantees you are looking for and then running `codex` with `--sandbox danger-full-access` (or, more simply, the `--dangerously-bypass-approvals-and-sandbox` flag) within your container.

## Version control

Codex works best with your version control system and we recommend:

- Working on a feature branch and keep `git status` clean before delegating; this keeps Codex’s patches easy to isolate and revert.
- Requiring the agent to generate patches (`git diff`/`git apply`) rather than editing tracked files manually. Commit frequently so you can roll back in small increments if needed.
- Treating Codex suggestions like any other PR: run targeted verification, review diffs, and document decisions in commit messages for auditability.

## Monitoring and telemetry

Codex supports opt‑in monitoring via OpenTelemetry (OTEL) to help teams audit usage, investigate issues, and satisfy compliance requirements without weakening local security defaults. Telemetry is off by default and must be explicitly enabled in your config.

### Overview

- OTEL export is disabled by default to keep local runs self‑contained.
- When enabled, Codex emits structured log events covering conversations, API requests, streamed responses, user prompts (redacted by default), tool approval decisions, and tool results.
- All exported events are tagged with `service.name` (originator), CLI version, and an environment label to separate dev/staging/prod traffic.

### Enable OTEL (opt‑in)

Add an `[otel]` block to your Codex config (typically `~/.codex/config.toml`), choosing an exporter and whether prompt text can be logged.

```toml
[otel]
environment = "staging"   # dev | staging | prod
exporter = "none"          # none | otlp-http | otlp-grpc
log_user_prompt = false     # redact prompt text unless policy allows
```

- `exporter = "none"` leaves instrumentation active but does not send data anywhere.
- To send events to your own collector, pick one of:

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

Events are batched and flushed on shutdown. Only telemetry produced by Codex’s OTEL module is exported.

### Event categories

Representative event types include:

- `codex.conversation_starts` (model, reasoning settings, sandbox/approval policy)
- `codex.api_request` and `codex.sse_event` (durations, status, token counts)
- `codex.user_prompt` (length; content redacted unless explicitly enabled)
- `codex.tool_decision` (approved/denied, source: config vs. user)
- `codex.tool_result` (duration, success, output snippet)

For the full event catalog and configuration reference, see the Codex config documentation on GitHub: https://github.com/openai/codex/blob/main/docs/config.md#otel

### Security and privacy guidance

- Keep `log_user_prompt = false` unless policy explicitly permits storing prompt contents. Prompts can include source code and potentially sensitive data.
- Route telemetry only to collectors you control; apply retention limits and access controls aligned with your compliance requirements.
- Treat tool arguments and outputs as potentially sensitive. Favor redaction at the collector or SIEM when feasible.
- If you run the CLI with network disabled, OTEL export will be blocked. To export, either allow network in `workspace-write` mode for the OTEL endpoint or export from Codex Cloud with an allowlisted collector domain.
- Review events periodically for approval/sandbox changes and unexpected tool executions.

OTEL is optional and designed to complement, not replace, the sandbox and approval protections described above.

## Managed configuration

Enterprise admins can set safe defaults and organization policies using a managed configuration layer. Managed config is merged on top of a user’s local `config.toml` and takes precedence over any CLI `--config` overrides, setting the starting values when Codex launches. Users can still change those settings during a session; the managed defaults are reapplied the next time Codex starts.

### Precedence and layering

The effective config is assembled in this order (top overrides bottom):

- Managed preferences (macOS MDM; highest precedence)
- `managed_config.toml` (system/managed file)
- `config.toml` (user’s base config)

CLI `--config key=value` overrides are applied to the base but are superseded by the managed layers, so a run always starts from the managed defaults even if local flags are provided.

### Locations

- Linux/macOS (Unix): `/etc/codex/managed_config.toml`
- Windows/non‑Unix: `~/.codex/managed_config.toml`

If the file is missing, the managed layer is simply not applied.

### macOS managed preferences (MDM)

On macOS, admins can push a device profile that provides a base64‑encoded TOML payload at:

- Preference domain: `com.openai.codex`
- Key: `config_toml_base64`

This “managed preferences” layer is parsed as TOML and applied with the highest precedence, above `managed_config.toml`.

### MDM setup workflow

Codex honors standard macOS MDM payloads, so you can distribute settings with tooling like Jamf Pro, Fleet, or Kandji. A lightweight rollout looks like:

1. Build the managed payload TOML and encode it with `base64` (no wrapping).
2. Drop the string into your MDM profile under the `com.openai.codex` domain at `config_toml_base64`.
3. Push the profile, then ask users to restart Codex or rerun `codex config show --effective` to confirm the managed values are active.
4. When revoking or changing policy, update the managed payload; the CLI reads the refreshed preference the next time it launches.

Avoid embedding secrets or high-churn dynamic values in the payload; treat the managed TOML like any other mobileconfig setting under change control.

### Example managed_config.toml

```toml
# Set conservative defaults
approval_policy = "on-request"
sandbox_mode    = "workspace-write"

[sandbox_workspace_write]
network_access = false             # keep network disabled unless explicitly allowed

[otel]
environment = "prod"
exporter = "otlp-http"            # point at your collector
log_user_prompt = false            # keep prompts redacted
# exporter details live under exporter tables; see Monitoring and telemetry above
```

### Recommended guardrails

- Prefer `workspace-write` with approvals for most users; reserve full access for tightly controlled containers.
- Keep `network_access = false` unless your security review allowlists a collector or domains required by your workflows.
- Use managed config to pin OTEL settings (exporter, environment), but keep `log_user_prompt = false` unless your policy explicitly allows storing prompt contents.
- Periodically audit diffs between local `config.toml` and managed policy to catch drift; managed layers should win over local flags and files.